<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Retifica somente as horas previstas da matriz técnica documentada. */
final class TechnicalMatrixWorkloadCorrection
{
    public function correct(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($request, $apply, $expectedSnapshot): array {
            $this->check(($request['format'] ?? '') === 'documented-technical-matrix-workloads-v1'
                && ($request['user_confirmed'] ?? false) === true
                && preg_match('/^[a-f0-9]{64}$/', $request['source_pdf_sha256'] ?? ''), 'Fonte documental ou objetivo não confirmado.');
            $course = AcademicCourse::with('academicYear.periods')->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($request['course_id']);
            $this->check($course->stage === AcademicCourse::STAGE_TECHNICAL
                && $course->academicYear->school_id === $request['school_id']
                && $course->academicYear->reference_year === $request['reference_year'], 'Matriz fora do curso técnico documentado.');
            $components = $course->components()->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())->get();
            $classIds = $course->classes()->pluck('school_classes.id');
            $enrollments = StudentEnrollment::whereIn('school_class_id', $classIds)->whereHas('courses', fn ($q) => $q->whereKey($course->id))->orderBy('id')->get();
            $this->check($enrollments->isNotEmpty(), 'A matriz não possui turma com matrícula.');
            $targets = collect($request['components'] ?? []);
            $this->check($targets->count() === $components->where('active', true)->count()
                && $targets->pluck('id')->unique()->count() === $targets->count()
                && $targets->pluck('id')->sort()->values()->all() === $components->where('active', true)->pluck('id')->sort()->values()->all(), 'A relação não corresponde aos componentes ativos da matriz.');
            $before = ['course' => $course->getAttributes(), 'components' => $components->map->getAttributes()->all(),
                'periods' => $course->academicYear->periods->map->getAttributes()->all()];
            $protected = $this->protectedDigest($classIds->all(), $enrollments->modelKeys());
            $changes = [];
            $moduleHours = [];
            $beforeHours = 0;
            foreach ($components->where('active', true) as $component) {
                $target = $targets->firstWhere('id', $component->id);
                $this->check(array_diff(array_keys($target), ['id', 'workload_hours']) === []
                    && is_int($target['workload_hours'] ?? null) && $target['workload_hours'] > 0 && $target['workload_hours'] <= 10000
                    && ! $component->hasPeriodLoads()
                    && $component->starts_period_id !== null && $component->starts_period_id === $component->ends_period_id
                    && $course->academicYear->periods->contains('id', $component->starts_period_id), 'Carga horária ou módulo inválido.');
                $oldHours = $component->calculatedWorkloadHours($course);
                $beforeHours += $oldHours;
                $component->workload_hours = $target['workload_hours'];
                if ($component->isDirty('workload_hours')) {
                    $changes[] = ['id' => $component->id, 'name' => $component->name, 'from_hours' => $oldHours, 'to_hours' => $target['workload_hours']];
                }
                $moduleHours[$component->starts_period_id] = ($moduleHours[$component->starts_period_id] ?? 0) + $target['workload_hours'];
            }
            $expectedModules = $request['expected_module_hours'] ?? [];
            ksort($moduleHours);
            ksort($expectedModules);
            $this->check($moduleHours === $expectedModules && array_sum($moduleHours) === ($request['expected_total_hours'] ?? null), 'As horas não fecham os módulos e o total do projeto.');
            $course->setRelation('components', $components);
            $course->workload_hours = array_sum($moduleHours);
            $hash = hash('sha256', json_encode([$request, $before, $protected], JSON_THROW_ON_ERROR));
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'protected_sha256' => $protected,
                'course_id' => $course->id, 'classes' => $classIds->all(), 'enrollment_count' => $enrollments->count(),
                'from_hours' => round($beforeHours, 2), 'planned_hours' => array_sum($moduleHours), 'module_hours' => $moduleHours,
                'components_to_update' => count($changes), 'course_to_update' => $course->isDirty('workload_hours'), 'changes' => $changes];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'A matriz ou seus registros mudaram após a conferência.');
            if (! $changes && ! $course->isDirty('workload_hours')) {
                return $result;
            }
            $backup = 'diary-reconciliation/technical-matrix-workloads-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before], JSON_THROW_ON_ERROR)), 'Não foi possível guardar a matriz anterior.');
            chmod(Storage::disk('local')->path($backup), 0600);
            Model::withoutEvents(function () use ($changes, $course): void {
                foreach ($changes as $change) {
                    CurriculumComponent::findOrFail($change['id'])->update(['workload_hours' => $change['to_hours']]);
                }
                if ($course->isDirty('workload_hours')) {
                    $course->save();
                }
            });
            $this->check(hash_equals($protected, $this->protectedDigest($classIds->all(), $enrollments->modelKeys())), 'A retificação alteraria registros fora da matriz.');
            AuditLog::create(['school_id' => $request['school_id'], 'auditable_type' => AcademicCourse::class,
                'auditable_id' => $course->id, 'action' => 'updated', 'old_values' => $before, 'new_values' => $result,
                'metadata' => ['operation' => 'technical-matrix-workloads', 'source_pdf_sha256' => $request['source_pdf_sha256'], 'backup_path' => $backup]]);

            return $result + ['backup_path' => $backup];
        });
    }

    private function protectedDigest(array $classIds, array $enrollmentIds): string
    {
        $assessments = DB::table('diary_assessments')->whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $attendance = DB::table('diary_attendance_records')->whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $years = DB::table('student_academic_history_years')->whereIn('student_enrollment_id', $enrollmentIds)->orderBy('id')->get();

        return hash('sha256', json_encode([
            $assessments, $attendance, $years,
            DB::table('diary_assessment_results')->whereIn('diary_assessment_id', $assessments->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('diary_attendance_record_id', $attendance->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_contents')->whereIn('school_class_id', $classIds)->orderBy('id')->get(),
            DB::table('student_enrollments')->whereIn('id', $enrollmentIds)->orderBy('id')->get(),
            DB::table('student_period_convalidations')->whereIn('student_enrollment_id', $enrollmentIds)->orderBy('id')->get(),
            DB::table('student_academic_history_records')->whereIn('student_academic_history_year_id', $years->pluck('id'))->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
