<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class SchoolYearClassHourCorrection
{
    public function correct(int $schoolId, int $referenceYear, int $minutes, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        if ($schoolId <= 0 || $referenceYear < 1900 || $referenceYear > 2100 || $minutes !== 60) {
            throw new RuntimeException('Escola, ano ou duração inválidos para esta correção.');
        }

        return $apply
            ? DB::transaction(fn () => $this->execute($schoolId, $referenceYear, $minutes, true, $expectedSnapshot))
            : $this->execute($schoolId, $referenceYear, $minutes, false, null);
    }

    private function execute(int $schoolId, int $referenceYear, int $minutes, bool $apply, ?string $expectedSnapshot): array
    {
        $years = AcademicYear::where('school_id', $schoolId)->where('reference_year', $referenceYear)
            ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
        if ($years->isEmpty() || $years->contains(fn ($year) => $year->ends_at && $year->ends_at->year !== $referenceYear)) {
            throw new RuntimeException('Ano escolar ausente ou plurianual: a correção precisa de um calendário anual definido.');
        }
        $courses = AcademicCourse::with('components')->whereIn('academic_year_id', $years->pluck('id'))
            ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
        $classes = SchoolClass::whereIn('academic_year_id', $years->pluck('id'))->orderBy('id')->get();
        $enrollments = StudentEnrollment::whereIn('school_class_id', $classes->pluck('id'))->orderBy('id')->get();
        $histories = StudentAcademicHistoryYear::with('records.component')->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('source', 'system')->orderBy('id')->get();
        $snapshot = [
            'years' => $years->map->getAttributes()->all(), 'classes' => $classes->map->getAttributes()->all(),
            'courses' => $courses->map(fn ($row) => $row->getAttributes() + ['components' => $row->components->sortBy('id')->map->getAttributes()->values()->all()])->all(),
            'enrollments' => $enrollments->map->getAttributes()->all(), 'histories' => $histories->toArray(),
            'diary_sha256' => $this->diaryDigest($classes->pluck('id')->all()),
        ];
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $changes = [];
        $componentChanges = [];
        foreach ($courses as $course) {
            $oldMinutes = (int) $course->class_hour_minutes;
            $preview = clone $course;
            $preview->class_hour_minutes = $minutes;
            $hasCalculatedChange = false;
            $newHours = 0.0;
            foreach ($course->components as $component) {
                $newWorkload = $component->workload_hours;
                $newWeekly = $component->weekly_lessons;
                $legacyHours = $component->legacy_metadata['horas'] ?? null;
                if ($oldMinutes > 0 && $oldMinutes !== $minutes && is_numeric($legacyHours) && (float) $legacyHours > 0
                    && $component->legacy_source && $component->legacy_source === $course->legacy_source) {
                    $importedWeekly = max(1, (int) round(((float) $legacyHours * 60) / ($oldMinutes * 40)));
                    $importedHours = (int) round(($importedWeekly * $oldMinutes * 40) / 60);
                    // Reconhece exatamente a conversão produzida pelo importador.
                    // Valores já corrigidos ou editados manualmente são preservados.
                    if ($component->weekly_lessons === $importedWeekly && $component->workload_hours !== null
                        && (float) $component->workload_hours === (float) $importedHours) {
                        $newWorkload = (float) $legacyHours;
                        $newWeekly = max(1, (int) round(((float) $legacyHours * 60) / ($minutes * 40)));
                    }
                }
                if ((float) $newWorkload !== (float) $component->workload_hours || $newWeekly !== $component->weekly_lessons) {
                    $componentChanges[$component->id] = ['workload_hours' => $newWorkload, 'weekly_lessons' => $newWeekly];
                    $hasCalculatedChange = true;
                }
                if ($component->active) {
                    $newHours += $newWorkload !== null ? (float) $newWorkload : $component->calculatedWorkloadHours($preview);
                    $hasCalculatedChange = $hasCalculatedChange || ($newWorkload === null && $oldMinutes !== $minutes);
                }
            }
            $newTotal = $hasCalculatedChange ? (int) round($newHours) : $course->workload_hours;
            if ($oldMinutes !== $minutes || $newTotal !== $course->workload_hours) {
                $changes[$course->id] = ['class_hour_minutes' => $minutes, 'workload_hours' => $newTotal];
            }
        }
        $result = [
            'mode' => $apply ? 'apply' : 'dry-run', 'school_id' => $schoolId, 'reference_year' => $referenceYear,
            'class_hour_minutes' => $minutes, 'snapshot_sha256' => $hash, 'diary_sha256' => $snapshot['diary_sha256'],
            'classes_count' => $classes->count(), 'courses_count' => $courses->count(),
            'academic_years_to_update' => $years->where('class_hour_minutes', '!=', $minutes)->count(),
            'courses_to_update' => count($changes), 'components_to_restore' => count($componentChanges),
            'history_years_count' => $histories->count(),
            'courses' => $courses->map(fn ($row) => [
                'id' => $row->id, 'name' => $row->name, 'minutes_before' => $row->class_hour_minutes,
                'minutes_after' => $minutes, 'hours_before' => $row->workload_hours,
                'hours_after' => $changes[$row->id]['workload_hours'] ?? $row->workload_hours,
                'components' => $row->components->map(fn ($component) => [
                    'id' => $component->id, 'name' => $component->name,
                    'hours_before' => $component->workload_hours,
                    'hours_after' => $componentChanges[$component->id]['workload_hours'] ?? $component->workload_hours,
                ])->all(),
            ])->all(),
        ];
        if (! $apply) {
            return $result;
        }
        if (! $expectedSnapshot || ! hash_equals($hash, $expectedSnapshot)) {
            throw new RuntimeException('O cadastro mudou desde a simulação; faça uma nova conferência.');
        }
        $backup = 'diary-reconciliation/class-hours-'.$schoolId.'-'.$referenceYear.'-'.Str::uuid().'.json';
        if (! Storage::disk('local')->put($backup, json_encode($snapshot, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Não foi possível salvar a cópia de segurança.');
        }
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($years, $courses, $minutes, $changes, $componentChanges, $histories): void {
            foreach ($years as $year) {
                if ((int) $year->class_hour_minutes !== $minutes) {
                    $year->update(['class_hour_minutes' => $minutes]);
                }
            }
            foreach ($courses as $course) {
                if (isset($changes[$course->id])) {
                    $course->update($changes[$course->id]);
                }
                foreach ($course->components as $component) {
                    if (isset($componentChanges[$component->id])) {
                        $component->update($componentChanges[$component->id]);
                    }
                }
            }
            foreach ($histories as $year) {
                if ($year->transcript_mode === 'no_transcription') {
                    continue;
                }
                $report = app(StudentReportCardBuilder::class)->build($year->enrollment->fresh());
                foreach ($year->records as $record) {
                    $matches = $report['annualComponents']->filter(fn ($item) => $record->curriculum_component_id
                        ? $item['component']->id === $record->curriculum_component_id
                        : $this->normalized($item['component']->name) === $this->normalized($record->component->name));
                    if ($matches->count() !== 1) {
                        throw new RuntimeException('Um registro do histórico não corresponde univocamente à matriz.');
                    }
                    $item = $matches->first();
                    $record->fill([
                        'workload_hours' => $item['component']->calculatedWorkloadHours(),
                        'completed_workload_hours' => app(AttendanceSummaryCalculator::class)->completedWorkloadHours($item['attendance']),
                    ]);
                    if ($record->isDirty()) {
                        $record->save();
                    }
                }
                $year->fill(['workload_hours' => $year->records->sum('workload_hours')]);
                if ($year->isDirty()) {
                    $year->save();
                }
            }
        });
        if (! hash_equals($snapshot['diary_sha256'], $this->diaryDigest($classes->pluck('id')->all()))) {
            throw new RuntimeException('Notas ou chamadas sofreram alteração; a correção foi revertida.');
        }
        AuditLog::create([
            'school_id' => $schoolId, 'auditable_type' => AcademicYear::class, 'auditable_id' => $years->first()->id,
            'action' => 'updated', 'old_values' => ['snapshot_sha256' => $hash], 'new_values' => ['class_hour_minutes' => $minutes],
            'metadata' => ['operation' => 'school-year-class-hour-correction', 'reference_year' => $referenceYear, 'backup_path' => $backup],
        ]);

        return $result + ['backup_path' => $backup, 'history_years_refreshed' => $histories->count()];
    }

    private function diaryDigest(array $classIds): string
    {
        $assessments = DB::table('diary_assessments')->whereIn('school_class_id', $classIds)->orderBy('id')->get();
        $attendance = DB::table('diary_attendance_records')->whereIn('school_class_id', $classIds)->orderBy('id')->get();

        return hash('sha256', json_encode([
            $assessments, DB::table('diary_assessment_results')->whereIn('diary_assessment_id', $assessments->pluck('id'))->orderBy('id')->get(),
            $attendance, DB::table('diary_attendance_entries')->whereIn('diary_attendance_record_id', $attendance->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_contents')->whereIn('school_class_id', $classIds)->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    private function normalized(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }
}
