<?php

namespace App\Support;

use App\Models\AcademicCourse;

final class ComponentApprovalCriteria
{
    public const TECHNICAL_PASSING_AVERAGE = 6.0;

    /** A média modular não pode ser comparada com a soma anual dos bimestres. */
    public static function evaluate(array $summary, float $annualPassingPoints): array
    {
        $technical = $summary['component']->course?->stage === AcademicCourse::STAGE_TECHNICAL;
        $values = collect($summary['periods'] ?? [])->pluck('average.value')->filter(fn ($value) => $value !== null);
        $value = $technical ? ($values->isEmpty() ? null : (float) $values->avg()) : (float) ($summary['points'] ?? 0);
        $target = $technical ? self::TECHNICAL_PASSING_AVERAGE : $annualPassingPoints;

        return ['basis' => $technical ? 'component_average' : 'annual_points', 'value' => $value, 'target' => $target,
            'missing' => max(0, round($target - ($value ?? 0), 2)), 'passed' => $value !== null && $value >= $target];
    }
}
