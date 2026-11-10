<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistoryYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class LegacyClassDiaryReconciler
{
    public function reconcile(array $manifest, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply
            ? DB::transaction(fn () => $this->execute($manifest, true, $expectedSnapshot))
            : $this->execute($manifest, false, null);
    }

    private function execute(array $manifest, bool $apply, ?string $expectedSnapshot): array
    {
        $target = $manifest['target'] ?? [];
        $authoritative = ($manifest['source_authoritative'] ?? false) === true;
        $deferIncomplete = ($manifest['defer_incomplete_sessions'] ?? false) === true;
        $allowRounding = ($manifest['allow_grade_rounding'] ?? false) === true;
        $class = SchoolClass::with(['academicYear.periods', 'enrollments.student'])->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($target['class_id'] ?? 0);
        $course = AcademicCourse::with('components')->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($target['course_id'] ?? 0);
        $year = $class->academicYear;
        $this->require(($manifest['format'] ?? '') === 'legacy-diary-pdf-manifest-v1'
            && empty($manifest['failures']) && ! empty($manifest['documents']), 'Manifesto incompleto.');
        $this->require($year->id === ($target['academic_year_id'] ?? null)
            && $year->school_id === ($target['school_id'] ?? null)
            && $year->reference_year === ($target['reference_year'] ?? null)
            && $course->academic_year_id === $year->id
            && $class->courses()->whereKey($course->id)->exists(), 'O alvo não corresponde à turma e à matriz.');
        $matrix = $manifest['matrix'] ?? [];
        $minutes = $matrix['class_hour_minutes'] ?? null;
        $this->require(is_int($minutes) && $minutes > 0 && $minutes <= 120, 'Duração da hora-aula inválida.');
        $components = $course->components->keyBy(fn ($row) => $this->normalized($row->name));
        $this->require($components->count() === $course->components->count(), 'Componentes ambíguos na matriz.');
        $matrixComponents = collect($matrix['components'] ?? [])->keyBy(fn ($row) => $this->normalized($row['name']));
        $this->require($matrixComponents->keys()->sort()->values()->all() === $components->keys()->sort()->values()->all(), 'A matriz documentada precisa conter todos os componentes da turma.');
        $documents = collect($manifest['documents'])->keyBy(fn ($row) => $this->normalized($row['component_name']));
        $this->require($documents->count() === count($manifest['documents'])
            && $documents->keys()->sort()->values()->all() === $components->keys()->sort()->values()->all(), 'Documentos duplicados ou componentes ausentes.');
        $enrollments = $class->enrollments->keyBy(fn ($row) => $this->normalized($row->student->full_name));
        $this->require($enrollments->count() === $class->enrollments->count(), 'Estudantes ambíguos na turma.');
        $periods = $year->periods->keyBy('position');
        $records = DiaryAttendanceRecord::with('entries')->where('school_class_id', $class->id)
            ->whereIn('curriculum_component_id', $components->pluck('id'))->orderBy('id')->get();
        $this->require($records->count() === $records->unique(fn ($row) => $row->curriculum_component_id.':'.$row->class_date->toDateString())->count(), 'Há chamadas duplicadas para a mesma data.');
        $recordMap = $records->keyBy(fn ($row) => $row->curriculum_component_id.':'.$row->class_date->toDateString());
        $contents = DiaryContent::where('school_class_id', $class->id)->whereIn('curriculum_component_id', $components->pluck('id'))->orderBy('id')->get();
        $this->require($contents->count() === $contents->unique(fn ($row) => $row->curriculum_component_id.':'.$row->class_date->toDateString())->count(), 'Há conteúdos duplicados para a mesma data.');
        $contentMap = $contents->keyBy(fn ($row) => $row->curriculum_component_id.':'.$row->class_date->toDateString());
        $assessments = DiaryAssessment::with('results')->where('school_class_id', $class->id)->whereIn('curriculum_component_id', $components->pluck('id'))->orderBy('id')->get();
        $historyYears = StudentAcademicHistoryYear::with(['records.component'])->whereIn('student_enrollment_id', $class->enrollments->pluck('id'))->where('source', 'system')->orderBy('id')->get();
        $snapshot = [
            'class' => $class->getAttributes(), 'year' => $year->getAttributes(),
            'course' => $course->getAttributes(), 'components' => $course->components->sortBy('id')->map->getAttributes()->values()->all(),
            'enrollments' => $class->enrollments->sortBy('id')->map->getAttributes()->values()->all(),
            'records' => $records->toArray(), 'contents' => $contents->toArray(),
            'assessments' => $assessments->toArray(), 'history_years' => $historyYears->toArray(),
        ];
        $snapshotHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $stats = ['calls_to_create' => 0, 'entries_to_create' => 0, 'contents_to_create' => 0,
            'contents_to_fill' => 0, 'matrix_changes' => 0, 'existing_entries_preserved' => 0,
            'entries_to_correct' => 0, 'entries_to_remove' => 0,
            'sessions_missing_quantity' => 0, 'marks_outside_enrollment' => 0,
            'existing_grades_preserved' => $assessments->sum(fn ($row) => $row->results->count()),
            'documented_sessions' => 0, 'documented_lessons' => 0];
        $sessions = [];
        $documentedKeys = [];
        $gradeOnlyComponents = [];
        $deferred = [];
        foreach ($documents as $name => $document) {
            $component = $components[$name];
            $this->require(($document['reference_year'] ?? null) === $year->reference_year
                && ($document['source'] ?? '') === ($target['source'] ?? null)
                && $this->normalized($document['class_name'] ?? '') === $this->normalized($class->name)
                && empty($document['issues']) && preg_match('/^[a-f0-9]{64}$/', $document['sha256'] ?? ''), 'Documento fora do alvo ou com erro de extração.');
            $seenStudents = [];
            foreach ($document['students'] as $student) {
                $enrollment = $enrollments->get($this->normalized($student['student_name']));
                $this->require($enrollment && $enrollment->student->legacy_source === $target['source']
                    && (int) $enrollment->student->legacy_id === (int) $student['legacy_student_id'], 'A identidade de um estudante diverge do documento.');
                $seenStudents[] = $enrollment->id;
                foreach ($student['grades'] as $position => $score) {
                    // A conferência compara as notas persistidas, inclusive as
                    // recebidas de origem antes do ingresso nessa turma.
                    $period = $periods->get((int) $position);
                    $stored = $assessments->where('academic_period_id', $period?->id)->where('curriculum_component_id', $component->id)->values();
                    $current = app(DiaryGradeCalculator::class)->averages(collect([$enrollment]), $stored)[$enrollment->id]['value'];
                    $expected = $allowRounding ? app(DiaryGradeCalculator::class)->roundToNearestHalf((float) $score) : (float) $score;
                    $this->require($current !== null && abs((float) $current - $expected) < 0.001, 'Há divergência nas notas; a reconciliação não altera notas existentes.');
                }
            }
            $this->require(count(array_unique($seenStudents)) === $class->enrollments->count(), 'O diário não identifica todos os estudantes da turma.');
            if (empty($document['sessions']) && $deferIncomplete) {
                $gradeOnlyComponents[] = $component->id;
            }
            foreach ($document['sessions'] as $session) {
                $date = $session['date'] ?? '';
                $period = $periods->get($session['period'] ?? 0);
                $key = $component->id.':'.$date;
                $this->require(! isset($documentedKeys[$key]), 'Aula duplicada no documento.');
                $documentedKeys[$key] = true;
                $existing = $recordMap->get($key);
                $lessonCount = $session['lesson_count'] ?? 0;
                if (! $lessonCount && $deferIncomplete) {
                    $lessonCount = $existing?->lesson_count ?? 0;
                    if (! $lessonCount) {
                        $this->require($period && $date >= $period->starts_at->toDateString()
                            && $date <= $period->ends_at->toDateString(), 'Data sem quantidade fora do bimestre.');
                        $stats['sessions_missing_quantity']++;
                        $deferred[] = ['component_id' => $component->id, 'date' => $date, 'reason' => 'missing_lesson_count', 'file' => $document['file']];

                        continue;
                    }
                }
                $this->require($period && is_int($lessonCount) && $lessonCount > 0 && $lessonCount <= 12
                    && $date >= $period->starts_at->toDateString() && $date <= $period->ends_at->toDateString(), 'A data ou a quantidade de aulas diverge do bimestre.');
                $this->require(! $existing || ($existing->academic_period_id === $period->id && $existing->lesson_count === $lessonCount), 'Uma chamada existente conflita com a fonte.');
                $stats['calls_to_create'] += $existing ? 0 : 1;
                $attendance = [];
                $outsideEnrollmentIds = [];
                foreach ($session['attendance'] as $studentName => $mark) {
                    $enrollment = $enrollments->get($this->normalized($studentName));
                    $this->require($enrollment && in_array($mark, ['present', 'absent'], true), 'Estudante ou marcação desconhecida.');
                    $inEnrollment = (! $enrollment->enrolled_at || $date >= $enrollment->enrolled_at->toDateString())
                        && (! $enrollment->attendanceEndsAt() || $date <= $enrollment->attendanceEndsAt());
                    if (! $inEnrollment && $deferIncomplete) {
                        $stats['marks_outside_enrollment']++;
                        $outsideEnrollmentIds[] = $enrollment->id;
                        $deferred[] = ['component_id' => $component->id, 'date' => $date, 'enrollment_id' => $enrollment->id,
                            'reason' => 'outside_enrollment', 'file' => $document['file']];

                        continue;
                    }
                    $this->require($inEnrollment, 'Marcação fora do vínculo ou estudante desconhecido.');
                    $attended = $mark === 'present' ? $lessonCount : 0;
                    $entry = $existing?->entries->firstWhere('student_enrollment_id', $enrollment->id);
                    $conflict = $entry && ($entry->attended_lessons !== $attended
                        || $entry->status !== ($attended > 0 ? DiaryAttendanceRecord::STATUS_PRESENT : DiaryAttendanceRecord::STATUS_ABSENT));
                    $this->require(! $conflict || $authoritative, 'Uma presença existente conflita com a fonte.');
                    $stats[$entry ? ($conflict ? 'entries_to_correct' : 'existing_entries_preserved') : 'entries_to_create']++;
                    $attendance[$enrollment->id] = $attended;
                }
                $excludedIds = [];
                if ($authoritative) {
                    foreach ($session['not_enrolled'] ?? [] as $studentName) {
                        $enrollment = $enrollments->get($this->normalized($studentName));
                        $this->require($enrollment && ! array_key_exists($enrollment->id, $attendance)
                            && ! in_array($enrollment->id, $outsideEnrollmentIds, true), 'Exclusão de frequência ambígua.');
                        $excludedIds[] = $enrollment->id;
                    }
                }
                $removals = $existing?->entries->whereIn('student_enrollment_id', $excludedIds)->pluck('id')->all() ?? [];
                $stats['entries_to_remove'] += count($removals);
                $this->require(! $existing || $existing->entries->pluck('student_enrollment_id')->diff(array_merge(array_keys($attendance), $outsideEnrollmentIds, $excludedIds))->isEmpty(), 'Há marcações sem correspondência explícita no documento.');
                $content = trim((string) ($session['content'] ?? ''));
                $existingContent = $contentMap->get($key);
                if ($content !== '' && trim((string) $existingContent?->content) === '') {
                    $stats[$existingContent ? 'contents_to_fill' : 'contents_to_create']++;
                }
                $sessions[$key] = compact('component', 'period', 'date', 'lessonCount', 'attendance', 'content', 'existing', 'existingContent', 'removals') + ['file' => $document['file'], 'sha256' => $document['sha256']];
                $stats['documented_sessions']++;
                $stats['documented_lessons'] += $lessonCount;
            }
            $definition = $matrixComponents[$name];
            $this->require(is_numeric($definition['workload_hours'] ?? null) && $definition['workload_hours'] > 0
                && array_key_exists('weekly_lessons', $definition)
                && (($definition['weekly_lessons'] === null && $component->weekly_lessons === null)
                    || (is_int($definition['weekly_lessons']) && $definition['weekly_lessons'] > 0)), 'Definição inválida da matriz.');
            $stats['matrix_changes'] += (float) $component->workload_hours !== (float) $definition['workload_hours'] || $component->weekly_lessons !== $definition['weekly_lessons'] ? 1 : 0;
        }
        $this->require($records->reject(fn ($row) => in_array($row->curriculum_component_id, $gradeOnlyComponents, true))
            ->every(fn ($row) => isset($documentedKeys[$row->curriculum_component_id.':'.$row->class_date->toDateString()])), 'Há chamadas atuais sem correspondência nos documentos.');
        $planned = (float) $matrixComponents->sum('workload_hours');
        $stats['matrix_changes'] += (int) $course->class_hour_minutes !== $minutes || (float) $course->workload_hours !== $planned ? 1 : 0;
        $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'class_id' => $class->id, 'reference_year' => $year->reference_year,
            'snapshot_sha256' => $snapshotHash, 'manifest_sha256' => $manifestHash, 'stats' => $stats,
            'planned_hours' => $planned, 'class_hour_minutes' => $minutes, 'students_count' => $class->enrollments->count(),
            'deferred' => $deferred, 'grade_only_component_ids' => $gradeOnlyComponents];
        if (! $apply) {
            return $result;
        }
        $this->require($expectedSnapshot && hash_equals($snapshotHash, $expectedSnapshot), 'O estado da turma mudou após a conferência. Execute uma nova simulação.');
        $backup = 'diary-reconciliation/backup-'.$class->id.'-'.Str::uuid().'.json';
        $this->require(Storage::disk('local')->put($backup, json_encode(['manifest_sha256' => $manifestHash, 'before' => $snapshot], JSON_THROW_ON_ERROR)), 'Não foi possível salvar a cópia de segurança.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($class, $course, $components, $matrixComponents, $minutes, $planned, $sessions, $historyYears): void {
            $course->update(['class_hour_minutes' => $minutes, 'workload_hours' => $planned]);
            foreach ($components as $name => $component) {
                $component->update(['workload_hours' => $matrixComponents[$name]['workload_hours'], 'weekly_lessons' => $matrixComponents[$name]['weekly_lessons']]);
            }
            $newEntries = [];
            $timestamp = now();
            foreach ($sessions as $session) {
                $record = $session['existing'] ?: DiaryAttendanceRecord::create([
                    'school_class_id' => $class->id, 'curriculum_component_id' => $session['component']->id,
                    'academic_period_id' => $session['period']->id, 'class_date' => $session['date'],
                    'lesson_count' => $session['lessonCount'], 'legacy_source' => 'pdf-reconciliation',
                    'legacy_metadata' => ['source_file' => $session['file'], 'source_sha256' => $session['sha256']],
                ]);
                foreach ($session['attendance'] as $enrollmentId => $attended) {
                    $entry = $session['existing']?->entries->firstWhere('student_enrollment_id', $enrollmentId);
                    $status = $attended > 0 ? DiaryAttendanceRecord::STATUS_PRESENT : DiaryAttendanceRecord::STATUS_ABSENT;
                    if ($entry && $entry->attended_lessons === $attended && $entry->status === $status) {
                        continue;
                    }
                    $values = [
                        'diary_attendance_record_id' => $record->id, 'student_enrollment_id' => $enrollmentId,
                        'status' => $attended > 0 ? DiaryAttendanceRecord::STATUS_PRESENT : DiaryAttendanceRecord::STATUS_ABSENT,
                        'attended_lessons' => $attended,
                        'lesson_presence' => array_fill(0, $session['lessonCount'], $attended > 0),
                    ];
                    if ($entry) {
                        $entry->fill($values)->save();
                    } else {
                        $values['lesson_presence'] = json_encode($values['lesson_presence'], JSON_THROW_ON_ERROR);
                        $newEntries[] = $values + ['created_at' => $timestamp, 'updated_at' => $timestamp];
                    }
                }
                DiaryAttendanceEntry::whereIn('id', $session['removals'])->delete();
                if ($session['content'] !== '' && trim((string) $session['existingContent']?->content) === '') {
                    $content = $session['existingContent'] ?: new DiaryContent([
                        'school_class_id' => $class->id, 'curriculum_component_id' => $session['component']->id,
                        'academic_period_id' => $session['period']->id, 'class_date' => $session['date'],
                        'legacy_source' => 'pdf-reconciliation',
                        'legacy_metadata' => ['source_file' => $session['file'], 'source_sha256' => $session['sha256']],
                    ]);
                    $content->fill(['content' => $session['content']])->save();
                }
            }
            foreach (array_chunk($newEntries, 500) as $chunk) {
                DiaryAttendanceEntry::insert($chunk);
            }
            foreach ($historyYears as $historyYear) {
                $report = app(StudentReportCardBuilder::class)->build($historyYear->enrollment->fresh());
                foreach ($historyYear->records as $historyRecord) {
                    $summary = $report['annualComponents']->first(fn ($item) => $historyRecord->curriculum_component_id
                        ? $item['component']->id === $historyRecord->curriculum_component_id
                        : $this->normalized($item['component']->name) === $this->normalized($historyRecord->component->name));
                    $this->require($summary !== null, 'Um componente do histórico não corresponde à matriz.');
                    $attendance = $summary['attendance'];
                    $percentage = $attendance['percentage'];
                    $historyRecord->update([
                        'workload_hours' => $summary['component']->calculatedWorkloadHours(),
                        'completed_workload_hours' => app(AttendanceSummaryCalculator::class)->completedWorkloadHours($attendance),
                        'absences' => $attendance['absent'], 'frequency_percentage' => $percentage,
                        'frequency_label' => $percentage !== null ? number_format($percentage, 2, ',', '.').'%' : null,
                    ]);
                }
                $historyYear->update([
                    'workload_hours' => $report['annualComponents']->sum(fn ($item) => $item['component']->calculatedWorkloadHours()),
                    'attendance_label' => $report['annualAttendance']['percentage'] !== null
                        ? number_format($report['annualAttendance']['percentage'], 2, ',', '.').'%' : null,
                ]);
            }
        });
        $this->require($assessments->toArray() === DiaryAssessment::with('results')->where('school_class_id', $class->id)
            ->whereIn('curriculum_component_id', $components->pluck('id'))->orderBy('id')->get()->toArray(), 'Uma nota foi alterada durante a reconciliação.');
        AuditLog::create([
            'school_id' => $year->school_id, 'auditable_type' => SchoolClass::class, 'auditable_id' => $class->id,
            'action' => 'updated', 'old_values' => ['snapshot_sha256' => $snapshotHash],
            'new_values' => ['class_hour_minutes' => $minutes, 'planned_hours' => $planned],
            'metadata' => ['operation' => 'legacy-class-diary-reconciliation', 'manifest_sha256' => $manifestHash,
                'backup_path' => $backup, 'stats' => $stats],
        ]);

        return $result + ['backup_path' => $backup, 'history_years_refreshed' => $historyYears->count(),
            'students' => $class->enrollments->map(function ($row) {
                $report = app(StudentReportCardBuilder::class)->build($row->fresh());

                return ['enrollment_id' => $row->id, 'attendance' => $report['annualAttendance'],
                    'completed_hours' => app(AttendanceSummaryCalculator::class)->completedWorkloadHours($report['annualAttendance'])];
            })->all()];
    }

    private function normalized(string $value): string
    {
        $value = preg_replace('/([1-9])\s*[º°ª]?\s*(ano|s[eé]rie)/iu', '$1 ano', $value);

        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
