<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\DiaryAssessment;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;

class DiaryGradeCalculator
{
    /**
     * @param Collection<int, StudentEnrollment> $enrollments
     * @param Collection<int, DiaryAssessment> $assessments
     * @return array<int, array{value: float|null, completed_assessments: int, total_assessments: int, complete: bool}>
     */
    public function averages(Collection $enrollments, Collection $assessments): array
    {
        $averages = [];
        $regularAssessments = $assessments->where('is_recovery', false)->values();
        $recoveryAssessment = $assessments->firstWhere('is_recovery', true);

        foreach ($enrollments as $enrollment) {
            $weightedScore = 0.0;
            $totalWeight = 0;
            $completedAssessments = 0;
            $scores = [];

            foreach ($regularAssessments as $assessment) {
                $result = $assessment->results->firstWhere('student_enrollment_id', $enrollment->id);

                if ($result?->score === null) {
                    continue;
                }

                $scores[$assessment->id] = (float) $result->score;
                $completedAssessments++;
            }

            $recoveryScore = $recoveryAssessment?->results->firstWhere('student_enrollment_id', $enrollment->id)?->score;
            if ($recoveryScore !== null) {
                if ($recoveryAssessment->recovery_mode === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT) {
                    $target = $regularAssessments->firstWhere('school_assessment_rule_id', $recoveryAssessment->recovery_replaced_rule_id);
                    if ($target && array_key_exists($target->id, $scores)) {
                        $scores[$target->id] = (float) $recoveryScore;
                    }
                }

                if ($recoveryAssessment->recovery_mode === AcademicPeriod::RECOVERY_REPLACE_LOWEST && $scores !== []) {
                    $lowestAssessmentId = collect($scores)
                        ->sortBy(fn (float $score, int $assessmentId): float => $score / (float) $regularAssessments->firstWhere('id', $assessmentId)->maximum_score)
                        ->keys()
                        ->first();
                    $scores[$lowestAssessmentId] = (float) $recoveryScore;
                }
            }

            foreach ($regularAssessments as $assessment) {
                if (! array_key_exists($assessment->id, $scores)) {
                    continue;
                }

                $weightedScore += ($scores[$assessment->id] / (float) $assessment->maximum_score) * 10 * (int) $assessment->weight;
                $totalWeight += (int) $assessment->weight;
            }

            if ($recoveryScore !== null && $recoveryAssessment?->recovery_mode === AcademicPeriod::RECOVERY_WEIGHTED) {
                $weightedScore += ((float) $recoveryScore / (float) $recoveryAssessment->maximum_score) * 10 * (int) $recoveryAssessment->weight;
                $totalWeight += (int) $recoveryAssessment->weight;
            }

            $averages[$enrollment->id] = [
                'value' => $totalWeight > 0 ? $this->roundToNearestHalf($weightedScore / $totalWeight) : null,
                'completed_assessments' => $completedAssessments,
                'total_assessments' => $regularAssessments->count(),
                'complete' => $regularAssessments->isNotEmpty() && $completedAssessments === $regularAssessments->count(),
            ];
        }

        return $averages;
    }

    public function roundToNearestHalf(float $value): float
    {
        return round($value * 2) / 2;
    }
}
