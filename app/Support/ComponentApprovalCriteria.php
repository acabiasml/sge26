<?php

namespace App\Support;

use App\Models\AcademicCourse;

final class ComponentApprovalCriteria
{
    public const TECHNICAL_PASSING_POINTS_PER_PERIOD = 6.0;

    /** Soma dos pontos; a meta do técnico acompanha os períodos do componente. */
    public static function evaluate(array $summary, float $annualPassingPoints): array
    {
        $technical = $summary['component']->course?->stage === AcademicCourse::STAGE_TECHNICAL;
        $values = collect($summary['periods'] ?? [])->pluck('average.value')->filter(fn ($value) => $value !== null);
        $value = $technical && $values->isEmpty() ? null : (float) ($summary['points'] ?? 0);
        $periodCount = max(1, (int) ($summary['total_periods'] ?? $values->count()));
        $target = $technical ? self::TECHNICAL_PASSING_POINTS_PER_PERIOD * $periodCount : $annualPassingPoints;

        return ['basis' => $technical ? 'period_points' : 'annual_points', 'value' => $value, 'target' => $target,
            'missing' => max(0, round($target - ($value ?? 0), 2)), 'passed' => $value !== null && $value >= $target];
    }
}
