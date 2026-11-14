<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Retificação limitada às matrizes documentadas; notas e chamadas permanecem arquivadas. */
final class VerifiedMatrixCorrection
{
    public function reconcile(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $targets = $request['courses'] ?? [];
        $this->check(($request['format'] ?? '') === 'verified-matrix-correction-v1'
            && ($request['user_confirmed'] ?? false) === true && count($targets) >= 1 && count($targets) <= 10
            && count(array_unique(array_column($targets, 'course_id'))) === count($targets), 'Retificação de matriz não confirmada ou duplicada.');
        $courses = AcademicCourse::with(['academicYear', 'components', 'classes.enrollments'])
            ->whereIn('id', array_column($targets, 'course_id'))->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())->get();
        $this->check($courses->count() === count($targets), 'Matriz selecionada ausente.');
        $classIds = $courses->flatMap->classes->pluck('id')->unique()->values()->all();
        $enrollments = StudentEnrollment::whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $personIds = $enrollments->pluck('person_id')->unique()->values()->all();
        $cache = StudentAcademicHistoryYear::where('source', 'system')->whereIn('student_enrollment_id', $enrollments->modelKeys())
            ->with('records')->orderBy('id')->get();
        $protected = $this->protectedDigest($personIds);
        $before = ['courses' => $courses->toArray(), 'enrollments' => $enrollments->toArray(), 'system_history' => $cache->toArray()];
        $componentChanges = [];
        $courseChanges = [];
        foreach ($targets as $target) {
            $course = $courses->firstWhere('id', $target['course_id']);
            $this->check($course->stage === AcademicCourse::STAGE_HIGH_SCHOOL
                && $course->academicYear->school_id === ($request['school_id'] ?? null)
                && $course->academicYear->reference_year === ($target['reference_year'] ?? null)
                && $course->classes->flatMap->enrollments->isNotEmpty()
                && preg_match('/^[a-f0-9]{64}$/', $target['source_pdf_sha256'] ?? '')
                && ($target['class_hour_minutes'] ?? 0) === 60, 'Matriz fora das turmas realizadas ou sem fonte documental.');
            $changes = $target['components'] ?? [];
            $this->check(count($changes) >= 1 && count(array_unique(array_column($changes, 'id'))) === count($changes), 'Componentes repetidos ou ausentes.');
            foreach ($changes as $change) {
                $component = $course->components->firstWhere('id', $change['id'] ?? 0);
                $this->check($component !== null && array_diff(array_keys($change), ['id', 'active', 'weekly_lessons', 'workload_hours']) === [], 'Componente ou campo fora da retificação.');
                $attributes = array_diff_key($change, ['id' => true]);
                $this->check((! isset($attributes['weekly_lessons']) || (is_int($attributes['weekly_lessons']) && $attributes['weekly_lessons'] > 0))
                    && (! isset($attributes['workload_hours']) || (is_numeric($attributes['workload_hours']) && $attributes['workload_hours'] > 0))
                    && (! isset($attributes['active']) || is_bool($attributes['active'])), 'Carga horária ou situação inválida.');
                $component->fill($attributes);
                if ($component->isDirty()) {
                    $componentChanges[$component->id] = $attributes;
                }
            }
            $total = $course->calculatedWorkloadHours();
            $this->check(abs($total - (float) ($target['expected_active_hours'] ?? -1)) < 0.01, 'As cargas dos componentes não fecham o total documentado.');
            $course->fill(['class_hour_minutes' => 60, 'workload_hours' => (int) round($total)]);
            if ($course->isDirty()) {
                $courseChanges[$course->id] = $course->getDirty();
            }
        }
        $refreshClassIds = $request['refresh_final_results_for_class_ids'] ?? [];
        $this->check(array_diff($refreshClassIds, $classIds) === [], 'Resultado final fora das turmas documentadas.');
        $finalPlans = [];
        foreach ($enrollments->whereIn('school_class_id', $refreshClassIds) as $enrollment) {
            $this->check($enrollment->schoolClass->academicYear->isClosed(), 'Não apure o resultado de uma turma em andamento.');
            if ($enrollment->status !== StudentEnrollment::STATUS_ENROLLED || ! $enrollment->final_result_status) {
                continue;
            }
            $result = app(StudentFinalResultCalculator::class)->calculate($enrollment);
            if ($result['status'] !== $enrollment->final_result_status) {
                $finalPlans[] = ['enrollment_id' => $enrollment->id, 'person_id' => $enrollment->person_id,
                    'from' => $enrollment->final_result_status, 'to' => $result['status']];
            }
        }
        $hash = hash('sha256', json_encode([$request, $before, $protected, $finalPlans], JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'protected_sha256' => $protected,
            'components_to_update' => count($componentChanges), 'courses_to_update' => count($courseChanges),
            'final_results_to_update' => count($finalPlans), 'final_results' => $finalPlans,
            'active_hours' => $courses->mapWithKeys(fn ($course) => [$course->id => $course->calculatedWorkloadHours()])->all()];
        if (! $apply) {
            return $result;
        }
        $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'A matriz ou seus lançamentos mudaram após a conferência.');
        if (! $componentChanges && ! $courseChanges && ! $finalPlans) {
            return $result;
        }
        $backup = 'diary-reconciliation/official-matrix-'.Str::uuid().'.json';
        $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before], JSON_THROW_ON_ERROR)), 'Falha ao guardar cópia das matrizes.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($componentChanges, $courseChanges, $refreshClassIds, $enrollments, $cache): void {
            foreach ($componentChanges as $id => $attributes) {
                CurriculumComponent::findOrFail($id)->update($attributes);
            }
            foreach ($courseChanges as $id => $attributes) {
                AcademicCourse::findOrFail($id)->update($attributes);
            }
            foreach ($enrollments->whereIn('school_class_id', $refreshClassIds) as $enrollment) {
                if ($enrollment->status !== StudentEnrollment::STATUS_ENROLLED || ! $enrollment->final_result_status) {
                    continue;
                }
                $computed = app(StudentFinalResultCalculator::class)->calculate($enrollment->fresh());
                if ($computed['status'] !== $enrollment->final_result_status) {
                    $enrollment->update(['final_result_status' => $computed['status'], 'final_result_details' => $computed['details'],
                        'final_result_calculated_at' => now()]);
                }
            }
            foreach ($cache as $year) {
                foreach ($year->records as $record) {
                    if (! isset($componentChanges[$record->curriculum_component_id])) {
                        continue;
                    }
                    $component = CurriculumComponent::findOrFail($record->curriculum_component_id);
                    if (! $component->active) {
                        $record->delete();
                    } else {
                        $record->update(['workload_hours' => $component->calculatedWorkloadHours()]);
                    }
                }
                $enrollment = StudentEnrollment::findOrFail($year->student_enrollment_id);
                $year->update(['workload_hours' => $year->records()->sum('workload_hours'), 'final_result' => $enrollment->finalResultLabel()]);
                $year->records()->update(['result' => $enrollment->finalResultLabel()]);
            }
        });
        $this->check(hash_equals($protected, $this->protectedDigest($personIds)), 'A retificação alteraria notas, presenças ou lançamentos manuais.');
        foreach ($courses as $course) {
            AuditLog::create(['school_id' => $request['school_id'], 'auditable_type' => AcademicCourse::class,
                'auditable_id' => $course->id, 'action' => 'updated', 'new_values' => $result,
                'metadata' => ['operation' => 'verified-matrix-correction', 'backup_path' => $backup, 'snapshot_sha256' => $hash]]);
        }

        return $result + ['backup_path' => $backup];
    }

    private function protectedDigest(array $personIds): string
    {
        $ids = StudentEnrollment::whereIn('person_id', $personIds)->pluck('id');
        $manual = StudentAcademicHistoryYear::where('source', '!=', 'system')->whereHas('history', fn ($q) => $q->whereIn('person_id', $personIds))
            ->with(['records' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get();

        return hash('sha256', json_encode([
            DB::table('diary_assessment_results')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('student_period_convalidations')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('academic_course_student_enrollment')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('people')->whereIn('id', $personIds)->orderBy('id')->get(),
            DB::table('person_school_roles')->whereIn('person_id', $personIds)->orderBy('id')->get(), $manual->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
