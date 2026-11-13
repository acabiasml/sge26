<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryExcludedDate;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Importação restrita a chamadas e conteúdos, sem alterar matrizes ou notas. */
final class DocumentedDiaryImporter
{
    public function reconcile(array $manifest, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($manifest, $apply, $expectedSnapshot): array {
            $this->check(($manifest['format'] ?? '') === 'documented-diary-import-v1'
                && preg_match('/^[a-f0-9]{64}$/', $manifest['source_sha256'] ?? '')
                && is_string($manifest['source_file'] ?? null), 'Fonte documental inválida.');
            $diaries = $manifest['diaries'] ?? [];
            $this->check(is_array($diaries) && count($diaries) >= 1 && count($diaries) <= 20
                && count(array_unique(array_column($diaries, 'assignment_id'))) === count($diaries), 'Diários duplicados ou inválidos.');
            $previous = $this->previousImport($manifest);
            $replacedRecords = collect();
            $replacedContents = collect();
            $snapshots = $plans = $results = [];
            foreach ($diaries as $diary) {
                $assignment = SchoolClassComponent::when($apply, fn ($q) => $q->lockForUpdate())
                    ->with(['schoolClass.academicYear.periods', 'schoolClass.academicYear.days',
                        'schoolClass.enrollments', 'schoolClass.startsPeriod', 'schoolClass.endsPeriod',
                        'component.course.startsPeriod', 'component.course.endsPeriod'])
                    ->findOrFail($diary['assignment_id'] ?? 0);
                $class = $assignment->schoolClass;
                $component = $assignment->component;
                $course = $component->course;
                $year = $class->academicYear;
                $this->check($assignment->active && $assignment->teacher_person_id === ($diary['teacher_person_id'] ?? null)
                    && $class->id === ($diary['class_id'] ?? null) && $component->id === ($diary['component_id'] ?? null)
                    && $year->school_id === ($diary['school_id'] ?? null) && $year->reference_year === ($diary['reference_year'] ?? null)
                    && $course->academic_year_id === $year->id && $class->courses()->whereKey($course->id)->exists(), 'O diário não corresponde ao alvo conferido.');
                $scope = fn ($q) => $q->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id);
                $records = DiaryAttendanceRecord::where($scope)->with('entries')->orderBy('id')->get();
                $contents = DiaryContent::where($scope)->orderBy('id')->get();
                $excluded = DiaryExcludedDate::where('school_class_component_id', $assignment->id)->get();
                $this->check($records->count() === $records->unique(fn ($row) => $row->class_date->toDateString())->count()
                    && $contents->count() === $contents->unique(fn ($row) => $row->class_date->toDateString())->count(), 'Existem datas duplicadas no diário.');
                $snapshots[] = [$assignment->getAttributes(), $class->getAttributes(), $year->getAttributes(),
                    $course->getAttributes(), $component->getAttributes(), $year->periods->toArray(), $year->days->toArray(),
                    $class->enrollments->sortBy('id')->values()->toArray(), $records->toArray(), $contents->toArray(), $excluded->toArray()];
                if ($previous) {
                    $oldDiary = collect($previous['diaries'])->firstWhere('assignment_id', $assignment->id);
                    $this->check($records->count() === count($oldDiary['sessions']) && $contents->count() === count($oldDiary['sessions'])
                        && $records->every(fn ($record) => blank($record->notes)
                            && $record->teacher_person_id === $assignment->teacher_person_id
                            && $record->created_at->equalTo($record->updated_at)
                            && $record->entries->every(fn ($entry) => $entry->created_at->equalTo($entry->updated_at)
                                && count($entry->lesson_presence ?? []) === $record->lesson_count
                                && collect($entry->lesson_presence)->every(fn ($present) => $present === true)))
                        && $contents->every(fn ($content) => $content->created_at->equalTo($content->updated_at)),
                        'Há edições posteriores ou registros fora da importação anterior.');
                    $replacedRecords = $replacedRecords->concat($records);
                    $replacedContents = $replacedContents->concat($contents);
                }
                $sessions = $diary['sessions'] ?? [];
                $this->check(is_array($sessions) && count($sessions) >= 1 && count($sessions) <= 250
                    && count(array_unique(array_column($sessions, 'date'))) === count($sessions), 'Datas documentadas duplicadas ou inválidas.');
                $stats = ['calls_to_create' => 0, 'contents_to_create' => 0, 'contents_to_fill' => 0,
                    'entries_to_create' => 0, 'existing_entries_preserved' => 0, 'outside_enrollment' => 0,
                    'calls_to_replace' => $previous ? $records->count() : 0, 'contents_to_replace' => $previous ? $contents->count() : 0];
                $lessonsByPeriod = [];
                foreach ($sessions as $session) {
                    $date = $session['date'] ?? '';
                    $count = $session['lesson_count'] ?? 0;
                    $content = trim((string) ($session['content'] ?? ''));
                    $this->check(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                        && CarbonImmutable::parse($date)->toDateString() === $date && $date <= now()->toDateString()
                        && is_int($count) && $count > 0 && $count <= 12 && $content !== '', 'Data, quantidade ou conteúdo inválido.');
                    $periods = $year->periods->filter(fn ($period) => $period->starts_at->toDateString() <= $date && $period->ends_at->toDateString() >= $date);
                    $this->check($periods->count() === 1, 'A data não corresponde a um único período letivo.');
                    $period = $periods->sole();
                    $lessonsByPeriod[$period->id] = ($lessonsByPeriod[$period->id] ?? 0) + $count;
                    $this->check($component->isActiveInPeriod($period)
                        && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
                        && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position)
                        && (! $class->startsPeriod || $period->position >= $class->startsPeriod->position)
                        && (! $class->endsPeriod || $period->position <= $class->endsPeriod->position)
                        && $year->days->contains(fn ($day) => $day->counts_as_school_day && $day->date->toDateString() === $date)
                        && ! $excluded->contains(fn ($day) => $day->class_date->toDateString() === $date), 'A data não é letiva para este diário.');
                    $record = $previous ? null : $records->first(fn ($row) => $row->class_date->toDateString() === $date);
                    $storedContent = $previous ? null : $contents->first(fn ($row) => $row->class_date->toDateString() === $date);
                    $this->check(! $record || ($record->lesson_count === $count && $record->academic_period_id === $period->id), 'Uma chamada existente diverge do lançamento.');
                    $this->check(! $storedContent || ($storedContent->academic_period_id === $period->id
                        && (trim((string) $storedContent->content) === '' || trim($storedContent->content) === $content)), 'Um conteúdo existente diverge do lançamento.');
                    $eligible = $class->enrollments->filter(fn ($enrollment) => $enrollment->includesAttendanceDate(CarbonImmutable::parse($date)));
                    $this->check($eligible->count() === $eligible->unique('person_id')->count(), 'Há matrículas sobrepostas na data.');
                    $this->check(! $record || $record->entries->every(fn ($entry) => $eligible->contains('id', $entry->student_enrollment_id)
                        && $entry->status === DiaryAttendanceRecord::STATUS_PRESENT && $entry->attended_lessons === $count), 'Existem marcações divergentes ou fora do vínculo.');
                    $stats['calls_to_create'] += $record ? 0 : 1;
                    $stats['contents_to_create'] += $storedContent ? 0 : 1;
                    $stats['contents_to_fill'] += $storedContent && trim((string) $storedContent->content) === '' ? 1 : 0;
                    $stats['existing_entries_preserved'] += $record?->entries->count() ?? 0;
                    $stats['entries_to_create'] += $eligible->count() - ($record?->entries->count() ?? 0);
                    $stats['outside_enrollment'] += $class->enrollments->count() - $eligible->count();
                    $plans[] = compact('assignment', 'period', 'date', 'count', 'content', 'record', 'storedContent', 'eligible');
                }
                $documentedDates = array_column($sessions, 'date');
                $this->check($previous || $records->every(fn ($row) => in_array($row->class_date->toDateString(), $documentedDates, true)), 'Há chamadas não contempladas no documento.');
                $hours = array_sum(array_column($sessions, 'lesson_count')) * $course->class_hour_minutes / 60;
                $this->check($course->class_hour_minutes > 0 && is_numeric($diary['expected_hours'] ?? null)
                    && abs($hours - $diary['expected_hours']) < 0.001, 'A quantidade de aulas não corresponde à carga conferida.');
                $results[] = ['assignment_id' => $assignment->id, 'class_id' => $class->id, 'component_id' => $component->id, 'school_id' => $year->school_id,
                    'sessions' => count($sessions), 'lessons' => array_sum(array_column($sessions, 'lesson_count')),
                    'hours' => $hours, 'lessons_by_period' => $lessonsByPeriod,
                    'students_count' => $class->enrollments->pluck('person_id')->unique()->count(), 'stats' => $stats];
            }
            $dailyLoad = null;
            if (isset($manifest['max_daily_minutes'])) {
                $this->check(is_int($manifest['max_daily_minutes']) && $manifest['max_daily_minutes'] > 0
                    && $manifest['max_daily_minutes'] <= 1440, 'Limite de carga diária inválido.');
                [$loadSnapshot, $dailyLoad] = $this->checkDailyLoad($plans, $manifest['max_daily_minutes'], $replacedRecords->pluck('id')->all());
                $snapshots[] = $loadSnapshot;
            }
            $hash = hash('sha256', json_encode([$manifest, $snapshots], JSON_THROW_ON_ERROR));
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'diaries' => $results, 'daily_load' => $dailyLoad];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot), 'O diário mudou após a conferência. Execute nova simulação.');
            $backup = 'diary-reconciliation/documented-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode(['manifest' => $manifest, 'before' => $snapshots], JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            foreach ($replacedRecords as $record) {
                $record->entries()->delete();
                $record->delete();
            }
            foreach ($replacedContents as $content) {
                $content->delete();
            }
            foreach ($plans as $plan) {
                $assignment = $plan['assignment'];
                $record = $plan['record'] ?? DiaryAttendanceRecord::create([
                    'school_class_id' => $assignment->school_class_id, 'curriculum_component_id' => $assignment->curriculum_component_id,
                    'teacher_person_id' => $assignment->teacher_person_id, 'academic_period_id' => $plan['period']->id,
                    'class_date' => $plan['date'], 'lesson_count' => $plan['count'],
                ]);
                foreach ($plan['eligible'] as $enrollment) {
                    if ($record->entries()->where('student_enrollment_id', $enrollment->id)->exists()) {
                        continue;
                    }
                    $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => DiaryAttendanceRecord::STATUS_PRESENT,
                        'attended_lessons' => $plan['count'], 'lesson_presence' => array_fill(0, $plan['count'], true)]);
                }
                $content = $plan['storedContent'] ?? new DiaryContent([
                    'school_class_id' => $assignment->school_class_id, 'curriculum_component_id' => $assignment->curriculum_component_id,
                    'academic_period_id' => $plan['period']->id, 'class_date' => $plan['date'],
                ]);
                if (trim((string) $content->content) === '') {
                    $content->fill(['content' => $plan['content']])->save();
                }
            }
            foreach ($results as $diary) {
                AuditLog::create(['school_id' => $diary['school_id'], 'auditable_type' => SchoolClassComponent::class,
                    'auditable_id' => $diary['assignment_id'], 'action' => 'updated',
                    'metadata' => ['operation' => 'documented-diary-import', 'source_sha256' => $manifest['source_sha256'],
                        'snapshot_sha256' => $hash, 'backup_path' => $backup, 'stats' => $diary['stats']]]);
            }

            return $result + ['backup_path' => $backup];
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    /** Considera aulas de todas as matrículas da pessoa, independentemente de presença. */
    private function checkDailyLoad(array $plans, int $limit, array $replacedRecordIds = []): array
    {
        $people = collect($plans)->flatMap(fn ($plan) => $plan['eligible']->pluck('person_id'))->unique();
        $enrollments = StudentEnrollment::whereIn('person_id', $people)->orderBy('id')->get();
        $byClass = $enrollments->groupBy('school_class_id');
        $records = DiaryAttendanceRecord::whereIn('school_class_id', $enrollments->pluck('school_class_id')->unique())
            ->where(function ($query) use ($plans) {
                foreach (array_unique(array_column($plans, 'date')) as $date) {
                    $query->orWhereDate('class_date', $date);
                }
            })
            ->with('component.course')->orderBy('id')->get();
        $minutesByStudentDate = [];
        $recordSnapshot = [];
        foreach ($records as $record) {
            $minutes = (int) $record->component->course->class_hour_minutes;
            $this->check($minutes > 0, 'Há aulas sem duração válida para conferir o limite diário.');
            $recordSnapshot[] = [$record->getAttributes(), $minutes];
            if (in_array($record->id, $replacedRecordIds, true)) {
                continue;
            }
            foreach ($byClass->get($record->school_class_id, collect())
                ->filter(fn ($enrollment) => $enrollment->includesAttendanceDate($record->class_date))->unique('person_id') as $enrollment) {
                $key = $enrollment->person_id.':'.$record->class_date->toDateString();
                $minutesByStudentDate[$key] = ($minutesByStudentDate[$key] ?? 0) + $record->lesson_count * $minutes;
            }
        }
        $checked = [];
        foreach ($plans as $plan) {
            $minutes = (int) $plan['assignment']->component->course->class_hour_minutes;
            foreach ($plan['eligible'] as $enrollment) {
                $key = $enrollment->person_id.':'.$plan['date'];
                $minutesByStudentDate[$key] ??= 0;
                if (! $plan['record']) {
                    $minutesByStudentDate[$key] += $plan['count'] * $minutes;
                }
                $checked[$key] = true;
            }
        }
        foreach (array_keys($checked) as $key) {
            $this->check($minutesByStudentDate[$key] <= $limit, 'O lançamento ultrapassa o limite diário de aulas de um estudante.');
        }
        $maximum = collect(array_keys($checked))->max(fn ($key) => $minutesByStudentDate[$key]) ?? 0;

        return [[$enrollments->toArray(), $recordSnapshot], ['limit_minutes' => $limit,
            'maximum_minutes' => $maximum, 'student_days_checked' => count($checked)]];
    }

    /** Substitui somente um lote anterior íntegro, identificado pela cópia e auditoria. */
    private function previousImport(array $manifest): ?array
    {
        $path = $manifest['replace_previous_import'] ?? null;
        if ($path === null) {
            return null;
        }
        $this->check(is_string($path) && preg_match('~^diary-reconciliation/documented-[a-f0-9-]{36}\.json$~', $path)
            && Storage::disk('local')->exists($path), 'Cópia da importação anterior inválida.');
        $backup = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        $previous = $backup['manifest'] ?? [];
        $this->check(($previous['format'] ?? '') === 'documented-diary-import-v1'
            && ! isset($previous['replace_previous_import']) && $previous['source_sha256'] === $manifest['source_sha256'], 'A fonte da importação anterior diverge.');
        $identity = fn ($diary) => collect($diary)->only(['assignment_id', 'teacher_person_id', 'class_id', 'component_id', 'school_id', 'reference_year', 'expected_hours'])->sortKeys()->all();
        $this->check(collect($previous['diaries'])->keyBy('assignment_id')->map($identity)->sortKeys()->all()
            === collect($manifest['diaries'])->keyBy('assignment_id')->map($identity)->sortKeys()->all(), 'O alvo ou a carga da redistribuição diverge.');
        foreach ($previous['diaries'] as $diary) {
            $this->check(AuditLog::where('auditable_type', SchoolClassComponent::class)->where('auditable_id', $diary['assignment_id'])
                ->where('metadata->operation', 'documented-diary-import')->where('metadata->backup_path', $path)->exists(),
                'A importação anterior não possui auditoria correspondente.');
        }
        $plan = $this->reconcile($previous);
        foreach ($plan['diaries'] as $diary) {
            $this->check($diary['stats']['calls_to_create'] === 0 && $diary['stats']['contents_to_create'] === 0
                && $diary['stats']['contents_to_fill'] === 0 && $diary['stats']['entries_to_create'] === 0,
                'A importação anterior não está íntegra para redistribuir.');
        }

        return $previous;
    }
}
