<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistoryYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class SchoolClassPeriodEndCorrection
{
    public function correct(int $schoolId, int $year, array $classIds, int $expectedEndId, int $newEndId, bool $apply = false, ?string $snapshot = null): array
    {
        if ($schoolId <= 0 || $year < 1900 || $year > 2100 || count($classIds) < 1 || count($classIds) > 30
            || count(array_unique($classIds)) !== count($classIds)
            || collect($classIds)->contains(fn ($id) => ! is_int($id) || $id <= 0)) {
            throw new RuntimeException('Recorte de turmas inválido.');
        }

        return DB::transaction(function () use ($schoolId, $year, $classIds, $expectedEndId, $newEndId, $apply, $snapshot): array {
            $classes = SchoolClass::whereIn('id', $classIds)->with(['academicYear.school', 'courses'])
                ->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())->get();
            $oldEnd = AcademicPeriod::findOrFail($expectedEndId);
            $newEnd = AcademicPeriod::findOrFail($newEndId);
            if ($classes->count() !== count($classIds) || $oldEnd->position !== 2 || $newEnd->position !== 4
                || $oldEnd->academic_year_id !== $newEnd->academic_year_id) {
                throw new RuntimeException('A retificação exige o II e o IV bimestres do mesmo calendário.');
            }
            $changes = [];
            foreach ($classes as $class) {
                if ($class->academicYear->school_id !== $schoolId || $class->academicYear->reference_year !== $year
                    || $class->academic_year_id !== $newEnd->academic_year_id
                    || ! $class->academicYear->isClosed()
                    || ! $class->courses->contains(fn ($c) => in_array($c->stage, ['fundamental', 'medio'], true))
                    || ! in_array($class->ends_period_id, [$expectedEndId, $newEndId], true)) {
                    throw new RuntimeException('Turma ou término fora da correção autorizada.');
                }
                if ($class->ends_period_id !== $newEndId) {
                    $changes[] = ['class_id' => $class->id, 'name' => $class->name,
                        'from_id' => $class->ends_period_id, 'from' => $oldEnd->name,
                        'to_id' => $newEndId, 'to' => $newEnd->name];
                }
            }
            $protected = $this->protectedDigest($classIds);
            $before = ['classes' => $classes->map->getAttributes()->all(),
                'periods' => [$oldEnd->getAttributes(), $newEnd->getAttributes()], 'protected_sha256' => $protected];
            $hash = hash('sha256', json_encode([$schoolId, $year, $classIds, $expectedEndId, $newEndId, $before], JSON_THROW_ON_ERROR));
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash,
                'protected_sha256' => $protected, 'classes_to_update' => count($changes), 'changes' => $changes,
                'current_ends' => $classes->map(fn ($c) => ['class_id' => $c->id, 'ends_period_id' => $c->ends_period_id])->all()];
            if (! $apply) {
                return $result;
            }
            if (! $snapshot || ! hash_equals($hash, $snapshot)) {
                throw new RuntimeException('Os cadastros ou lançamentos mudaram desde a conferência.');
            }
            if (! $changes) {
                return $result;
            }
            $backup = 'diary-reconciliation/class-period-end-'.Str::uuid().'.json';
            if (! Storage::disk('local')->put($backup, json_encode($before, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Falha ao guardar a cópia anterior dos términos.');
            }
            chmod(Storage::disk('local')->path($backup), 0600);
            Model::withoutEvents(function () use ($classes, $newEndId): void {
                foreach ($classes as $class) {
                    if ($class->ends_period_id !== $newEndId) {
                        $class->update(['ends_period_id' => $newEndId]);
                    }
                }
            });
            if (! hash_equals($protected, $this->protectedDigest($classIds))) {
                throw new RuntimeException('A retificação afetaria lançamentos escolares; operação revertida.');
            }
            foreach ($changes as $change) {
                AuditLog::create(['school_id' => $schoolId, 'auditable_type' => SchoolClass::class, 'auditable_id' => $change['class_id'],
                    'action' => 'updated', 'old_values' => ['ends_period_id' => $change['from_id']],
                    'new_values' => ['ends_period_id' => $newEndId],
                    'metadata' => ['operation' => 'class-period-end-correction', 'reference_year' => $year,
                        'backup_path' => $backup, 'reason' => 'Gestão confirmou término das turmas anuais no IV bimestre.']]);
            }

            return $result + ['backup_path' => $backup, 'ends_period_id_after' => $newEndId];
        });
    }

    private function protectedDigest(array $classIds): string
    {
        $assessments = DB::table('diary_assessments')->whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $attendance = DB::table('diary_attendance_records')->whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $enrollments = DB::table('student_enrollments')->whereIn('school_class_id', $classIds)->orderBy('id')->get();

        return hash('sha256', json_encode([$assessments, $attendance, $enrollments,
            DB::table('diary_assessment_results')->whereIn('diary_assessment_id', $assessments->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('diary_attendance_record_id', $attendance->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_contents')->whereIn('school_class_id', $classIds)->orderBy('id')->get(),
            DB::table('student_period_convalidations')->whereIn('student_enrollment_id', $enrollments->pluck('id'))->orderBy('id')->get(),
            StudentAcademicHistoryYear::whereIn('student_enrollment_id', $enrollments->pluck('id'))->with('records')->orderBy('id')->get()->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}
