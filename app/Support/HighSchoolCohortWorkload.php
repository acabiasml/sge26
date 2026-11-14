<?php

namespace App\Support;

use App\Models\AcademicCourse;

/** Confere a formação básica dos três anos, pela turma de ingresso. */
final class HighSchoolCohortWorkload
{
    public function target(int $entryYear): ?int
    {
        return match ($entryYear) {
            2022, 2023, 2024 => 1800,
            2025, 2026 => 2400,
            default => null,
        };
    }

    public function forCourse(AcademicCourse $course): ?array
    {
        if ($course->stage !== AcademicCourse::STAGE_HIGH_SCHOOL || $course->isItineraryMatrix()
            || ! $course->classes()->whereHas('enrollments')->exists()) {
            return null;
        }
        $grade = app(StudentAcademicHistoryCompleteness::class)->gradeNumber($course->name, $course->stage);
        $entryYear = (int) $course->academicYear->reference_year - (int) $grade + 1;
        $target = $grade ? $this->target($entryYear) : null;
        if ($target === null) {
            return null;
        }
        $matrices = AcademicCourse::where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)
            ->whereHas('academicYear', fn ($q) => $q->where('school_id', $course->academicYear->school_id)
                ->whereBetween('reference_year', [$entryYear, $entryYear + 2]))
            ->whereHas('classes', fn ($q) => $q->whereHas('enrollments'))
            ->with(['academicYear', 'components.area', 'components.historyCatalogEntries'])
            ->get()->filter(function ($matrix) use ($entryYear): bool {
                $grade = app(StudentAcademicHistoryCompleteness::class)->gradeNumber($matrix->name, $matrix->stage);

                return $grade && ! $matrix->isItineraryMatrix()
                    && (int) $matrix->academicYear->reference_year - $grade + 1 === $entryYear;
            });
        $years = $matrices->groupBy(fn ($matrix) => (int) $matrix->academicYear->reference_year - $entryYear + 1)
            ->sortKeys()->map(function ($matrices, $grade) use ($entryYear): array {
                $loads = $matrices->map(function ($matrix): float {
                    return round($matrix->components->where('active', true)->sum(function ($component) use ($matrix): float {
                        $component->setRelation('course', $matrix);

                        return CurriculumCatalog::formationLabelForComponent($component) === CurriculumCatalog::FORMATION_FGB
                            ? $component->calculatedWorkloadHours($matrix) : 0;
                    }), 2);
                })->unique()->values();

                return ['grade' => (int) $grade, 'year' => $entryYear + (int) $grade - 1,
                    'course_ids' => $matrices->modelKeys(), 'hours' => $loads->count() === 1 ? $loads->first() : null];
            });
        $missing = collect([1, 2, 3])->diff($years->keys())->values()->all();
        $ambiguous = $years->whereNull('hours')->keys()->values()->all();
        $hours = round($years->sum('hours'), 2);
        $difference = round($target - $hours, 2);
        $status = $ambiguous ? 'ambiguous' : ($hours > $target + 0.05 ? 'exceeded'
            : ($missing ? 'incomplete' : (abs($difference) <= 0.05 ? 'complete' : 'mismatch')));

        return ['entry_year' => $entryYear, 'target_hours' => $target, 'registered_hours' => $hours,
            'remaining_hours' => $difference, 'years' => $years->values()->all(),
            'missing_grades' => $missing, 'ambiguous_grades' => $ambiguous, 'status' => $status];
    }
}
