<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Restaura vínculos técnicos ausentes, sem estimar datas de transferência. */
final class TechnicalLegacyEnrollmentRestorer
{
    public function reconcile(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $this->check(($request['format'] ?? '') === 'technical-legacy-enrollments-v1'
            && ($request['inclusion_and_presence_approved'] ?? false) === true, 'Inclusão técnica não autorizada.');
        $course = AcademicCourse::with(['academicYear.school', 'academicYear.periods', 'components'])
            ->findOrFail($request['course_id'] ?? 0);
        $class = SchoolClass::with('courses')->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($request['class_id'] ?? 0);
        $source = $course->academicYear->school->legacy_source;
        $this->check($course->stage === AcademicCourse::STAGE_TECHNICAL
            && $course->academicYear->school_id === ($request['school_id'] ?? null)
            && $course->academicYear->reference_year === ($request['reference_year'] ?? null)
            && $class->academic_year_id === $course->academic_year_id && $class->courses->contains('id', $course->id)
            && in_array($source, ['beaba', 'lar', 'laura'], true), 'Turma técnica fora da escola e do ano conferidos.');
        $students = $request['students'] ?? [];
        $personIds = array_column($students, 'person_id');
        $this->check(count($students) >= 1 && count($students) <= 10
            && count(array_unique($personIds)) === count($students), 'Estudantes técnicos duplicados ou ausentes.');
        $through = $this->date($request['attendance_through'] ?? '');
        $this->check($through <= now()->toDateString(), 'Não é permitido lançar presenças futuras.');
        $path = Storage::disk('local')->path('u810745753_'.$source.'.sql');
        $this->check(is_file($path) && preg_match('/^[a-f0-9]{64}$/', $request['legacy_dump_sha256'] ?? '')
            && hash_equals($request['legacy_dump_sha256'], hash_file('sha256', $path)), 'A fonte legada mudou ou está ausente.');
        $calls = DiaryAttendanceRecord::with('entries')->where('school_class_id', $class->id)
            ->whereIn('curriculum_component_id', $course->components->modelKeys())->orderBy('id')->get();
        $protected = $this->protectedDigest($class, $personIds);
        $plans = [];
        $before = [];
        $totals = ['enrollments_to_create' => 0, 'grades_to_create' => 0, 'presences_to_create' => 0];
        foreach ($students as $student) {
            $person = Person::findOrFail($student['person_id'] ?? 0);
            $old = $student['legacy_row'] ?? [];
            $this->check($person->legacy_source === $source && (int) $person->legacy_id === (int) ($old['users_id'] ?? 0)
                && (int) ($old['id'] ?? 0) > 0 && (int) ($old['cursos_id'] ?? 0) === ($request['legacy_course_id'] ?? null)
                && ($old['tipo'] ?? '') === StudentEnrollment::TYPE_REGULAR
                && ($old['status'] ?? '') === StudentEnrollment::STATUS_ENROLLED, 'Identidade técnica divergente da matrícula original.');
            $start = $this->date($old['datamatricula'] ?? '');
            $transfer = isset($student['transferred_at']) ? $this->date($student['transferred_at']) : null;
            $transferYear = $student['confirmed_transfer_year'] ?? null;
            $this->check(is_int($transferYear) && $transferYear >= $course->academicYear->reference_year
                && ($transfer ? (int) substr($transfer, 0, 4) === $transferYear : $through < $transferYear.'-01-01')
                && $start >= $course->academicYear->starts_at->toDateString()
                && $start <= $through && (! $transfer || $start <= $transfer), 'A janela de presença depende da data de transferência.');
            $matches = StudentEnrollment::where('school_class_id', $class->id)->where('person_id', $person->id)->with('courses')->get();
            $this->check($matches->count() <= 1, 'Há mais de um vínculo técnico para o estudante.');
            $enrollment = $matches->first();
            $owner = StudentEnrollment::where('legacy_source', $source)->where('legacy_id', (int) $old['id'])->first();
            $this->check(! $owner || ($enrollment && $owner->id === $enrollment->id), 'Identificador legado pertence a outro vínculo.');
            if ($enrollment) {
                $this->check($enrollment->legacy_source === $source && (int) $enrollment->legacy_id === (int) $old['id']
                    && $enrollment->enrolled_at?->toDateString() === $start
                    && $enrollment->transferred_at?->toDateString() === $transfer
                    && $enrollment->status === StudentEnrollment::STATUS_TRANSFERRED
                    && $enrollment->courses->pluck('id')->all() === [$course->id], 'O vínculo existente diverge da restauração.');
            }
            $grades = $student['grades'] ?? [];
            $this->check(count($grades) >= 1 && count($grades) <= 20
                && count(array_unique(array_map(fn ($g) => ($g['component_id'] ?? 0).':'.($g['period_id'] ?? 0), $grades))) === count($grades), 'Médias técnicas ausentes ou duplicadas.');
            $gradePlans = [];
            foreach ($grades as $grade) {
                $component = $course->components->firstWhere('id', $grade['component_id'] ?? 0);
                $period = $course->academicYear->periods->firstWhere('id', $grade['period_id'] ?? 0);
                $score = $grade['score'] ?? null;
                $this->check($component && $period && $component->isActiveInPeriod($period)
                    && ($grade['source_grade_kind'] ?? '') === 'reported_average'
                    && preg_match('/^[a-f0-9]{64}$/', $grade['source_pdf_sha256'] ?? '')
                    && is_numeric($score) && $score >= 0 && $score <= 10, 'Média técnica sem fonte ou fora do módulo.');
                $assessments = DiaryAssessment::where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)
                    ->where('academic_period_id', $period->id)->get();
                $this->check($assessments->count() === 1 && ! $assessments->first()->is_recovery
                    && (float) $assessments->first()->maximum_score === 10.0, 'Módulo sem avaliação única compatível com a média.');
                $assessment = $assessments->first();
                $result = $enrollment ? DiaryAssessmentResult::where('diary_assessment_id', $assessment->id)->where('student_enrollment_id', $enrollment->id)->first() : null;
                $this->check(! $result || (float) $result->score === (float) $score, 'Existe uma nota diferente; é necessária conferência antes de substituí-la.');
                $gradePlans[] = ['assessment_id' => $assessment->id, 'score' => (float) $score, 'create' => ! $result];
            }
            $presencePlans = [];
            foreach ($calls as $call) {
                $date = $call->class_date->toDateString();
                if ($date < $start || $date > $through || ($transfer && $date > $transfer)) {
                    continue;
                }
                $period = $course->academicYear->periods->firstWhere('id', $call->academic_period_id);
                $component = $course->components->firstWhere('id', $call->curriculum_component_id);
                $this->check($period && $component->isActiveInPeriod($period) && $call->lesson_count > 0, 'Chamada técnica sem quantidade ou módulo válido.');
                $entry = $enrollment ? $call->entries->firstWhere('student_enrollment_id', $enrollment->id) : null;
                $this->check(! $entry || ($entry->status === DiaryAttendanceRecord::STATUS_PRESENT
                    && $entry->attended_lessons === $call->lesson_count), 'Há frequência existente divergente para o estudante.');
                if (! $entry) {
                    $presencePlans[] = ['record_id' => $call->id, 'lessons' => $call->lesson_count];
                }
            }
            $before[] = ['person' => $person->getAttributes(), 'enrollment' => $enrollment?->toArray(),
                'results' => $enrollment?->assessmentResults()->orderBy('id')->get()->toArray(),
                'entries' => $enrollment?->attendanceEntries()->orderBy('id')->get()->toArray()];
            $plans[] = ['person_id' => $person->id, 'enrollment_id' => $enrollment?->id, 'legacy_row' => $old,
                'start' => $start, 'transferred_at' => $transfer, 'confirmed_transfer_year' => $transferYear,
                'grades' => $gradePlans, 'presences' => $presencePlans];
            $totals['enrollments_to_create'] += $enrollment ? 0 : 1;
            $totals['grades_to_create'] += collect($gradePlans)->where('create', true)->count();
            $totals['presences_to_create'] += count($presencePlans);
        }
        $hash = hash('sha256', json_encode([$request, $before, $plans, $protected], JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'stats' => $totals,
            'attendance_through' => $through, 'pending_transfer_dates' => collect($plans)->whereNull('transferred_at')->pluck('person_id')->all(),
            'students' => array_map(fn ($p) => ['person_id' => $p['person_id'], 'enrollment_id' => $p['enrollment_id'],
                'enrolled_at' => $p['start'], 'transferred_at' => $p['transferred_at'],
                'grades_to_create' => collect($p['grades'])->where('create', true)->count(), 'presences_to_create' => count($p['presences'])], $plans)];
        if (! $apply) {
            return $result;
        }
        $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'Os dados mudaram após a simulação da inclusão técnica.');
        if (array_sum($totals) === 0) {
            return $result;
        }
        $backup = 'diary-reconciliation/technical-enrollments-'.Str::uuid().'.json';
        $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before, 'protected_sha256' => $protected], JSON_THROW_ON_ERROR)), 'Falha ao guardar cópia da inclusão.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($plans, $class, $course, $source, $request): void {
            foreach ($plans as $plan) {
                $enrollment = $plan['enrollment_id'] ? StudentEnrollment::findOrFail($plan['enrollment_id']) : StudentEnrollment::create([
                    'school_class_id' => $class->id, 'person_id' => $plan['person_id'], 'enrolled_at' => $plan['start'],
                    'transferred_at' => $plan['transferred_at'], 'status' => StudentEnrollment::STATUS_TRANSFERRED,
                    'type' => StudentEnrollment::TYPE_REGULAR, 'final_result_status' => StudentEnrollment::FINAL_TRANSFERRED,
                    'notes' => $plan['transferred_at'] ? 'Vínculo técnico restaurado conforme os diários e a transferência informada.'
                        : 'Transferência em '.$plan['confirmed_transfer_year'].' informada pela secretaria. Data exata pendente de confirmação; frequência posterior a 2025 não preenchida nesta restauração.',
                    'legacy_source' => $source, 'legacy_id' => (int) $plan['legacy_row']['id'],
                    'legacy_metadata' => ['latest_imported_row' => $plan['legacy_row'], 'restored_from_dump_sha256' => $request['legacy_dump_sha256'],
                        'confirmed_transfer_year' => $plan['confirmed_transfer_year'], 'transfer_date_pending' => ! $plan['transferred_at']],
                ]);
                if (! $plan['enrollment_id']) {
                    $enrollment->courses()->attach($course->id);
                }
                foreach ($plan['grades'] as $grade) {
                    if ($grade['create']) {
                        DiaryAssessmentResult::create(['diary_assessment_id' => $grade['assessment_id'],
                            'student_enrollment_id' => $enrollment->id, 'score' => $grade['score']]);
                    }
                }
                foreach ($plan['presences'] as $presence) {
                    DiaryAttendanceEntry::create(['diary_attendance_record_id' => $presence['record_id'],
                        'student_enrollment_id' => $enrollment->id, 'status' => DiaryAttendanceRecord::STATUS_PRESENT,
                        'attended_lessons' => $presence['lessons'], 'lesson_presence' => array_fill(0, $presence['lessons'], true)]);
                }
            }
        });
        $this->check(hash_equals($protected, $this->protectedDigest($class, $personIds)), 'A inclusão alteraria outros registros escolares.');
        AuditLog::create(['school_id' => $course->academicYear->school_id, 'auditable_type' => SchoolClass::class,
            'auditable_id' => $class->id, 'action' => 'updated', 'new_values' => $totals,
            'metadata' => ['operation' => 'technical-legacy-enrollment-restoration', 'backup_path' => $backup,
                'snapshot_sha256' => $hash, 'attendance_through' => $through, 'pending_transfer_dates' => $result['pending_transfer_dates']]]);

        return $result + ['backup_path' => $backup];
    }

    private function protectedDigest(SchoolClass $class, array $personIds): string
    {
        $targetIds = StudentEnrollment::where('school_class_id', $class->id)->whereIn('person_id', $personIds)->pluck('id');
        $enrollments = DB::table('student_enrollments')->whereNotIn('id', $targetIds)
            ->where(fn ($q) => $q->where('school_class_id', $class->id)->orWhereIn('person_id', $personIds))->orderBy('id')->get();
        $assessmentIds = DB::table('diary_assessments')->where('school_class_id', $class->id)->pluck('id');
        $callIds = DB::table('diary_attendance_records')->where('school_class_id', $class->id)->pluck('id');
        $class->load('academicYear.periods', 'courses.components');

        return hash('sha256', json_encode([$class->toArray(), $enrollments,
            DB::table('academic_course_student_enrollment')->whereIn('student_enrollment_id', $enrollments->pluck('id'))->orderBy('student_enrollment_id')->orderBy('academic_course_id')->get(),
            DB::table('people')->whereIn('id', $personIds)->orderBy('id')->get(),
            DB::table('person_school_roles')->whereIn('person_id', $personIds)->orderBy('id')->get(),
            DB::table('diary_assessments')->where('school_class_id', $class->id)->orderBy('id')->get(),
            DB::table('diary_attendance_records')->where('school_class_id', $class->id)->orderBy('id')->get(),
            DB::table('diary_contents')->where('school_class_id', $class->id)->orderBy('id')->get(),
            DB::table('diary_assessment_results')->whereIn('diary_assessment_id', $assessmentIds)->whereNotIn('student_enrollment_id', $targetIds)->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('diary_attendance_record_id', $callIds)->whereNotIn('student_enrollment_id', $targetIds)->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    private function date(string $value): string
    {
        $this->check((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && Carbon::parse($value)->toDateString() === $value, 'Data inválida.');

        return $value;
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
