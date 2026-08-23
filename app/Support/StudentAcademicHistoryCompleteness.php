<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Str;

class StudentAcademicHistoryCompleteness
{
    /** @return array{complete: bool, stage: string|null, target_grade: int|null, registered_grades: list<int>, missing_grades: list<int>, message: string|null} */
    public function evaluate(StudentAcademicHistory $history, ?int $targetGrade = null): array
    {
        $stage = $history->education_stage;
        $registered = $history->years
            ->map(fn ($year): ?int => $this->gradeNumber((string) ($year->grade_phase ?: $year->label), $stage))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $targetGrade ??= $registered->max();
        $maximum = $stage === AcademicCourse::STAGE_ELEMENTARY ? 9 : ($stage === AcademicCourse::STAGE_HIGH_SCHOOL ? 3 : null);

        if (! $maximum || ! $targetGrade || $targetGrade < 1 || $targetGrade > $maximum) {
            return $this->result($stage, $targetGrade, $registered->all(), []);
        }

        $required = $targetGrade === 1 ? collect() : collect(range(1, $targetGrade - 1));
        $missing = $required->diff($registered)->values()->all();

        return $this->result($stage, $targetGrade, $registered->all(), $missing);
    }

    public function gradeNumber(string $label, ?string $stage): ?int
    {
        if (! in_array($stage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL], true)) {
            return null;
        }

        $normalized = Str::of($label)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
        if (preg_match('/\b([1-9])\s*(?:o|a)?\s*(?:ano|serie)\b/u', $normalized, $matches) !== 1) {
            return null;
        }

        $grade = (int) $matches[1];

        return $stage === AcademicCourse::STAGE_HIGH_SCHOOL && $grade > 3 ? null : $grade;
    }

    /** @param list<int> $registered @param list<int> $missing */
    private function result(?string $stage, ?int $targetGrade, array $registered, array $missing): array
    {
        $stageLabel = AcademicCourse::STAGE_LABELS[$stage] ?? 'etapa';
        $labels = collect($missing)->map(fn (int $grade): string => "{$grade}º ano")->join(', ');

        return [
            'complete' => $missing === [],
            'stage' => $stage,
            'target_grade' => $targetGrade,
            'registered_grades' => $registered,
            'missing_grades' => $missing,
            'message' => $missing === [] ? null : "Histórico incompleto para {$stageLabel}: cadastre {$labels} antes da emissão.",
        ];
    }
}
