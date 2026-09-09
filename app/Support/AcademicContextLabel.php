<?php

namespace App\Support;

use App\Models\AcademicCourse;
use Illuminate\Support\Collection;

final class AcademicContextLabel
{
    /**
     * @param  iterable<int, AcademicCourse>  $courses
     * @return Collection<int, string>
     */
    public static function stageLabels(iterable $courses): Collection
    {
        return collect($courses)
            ->map(fn (AcademicCourse $course): string => __($course->stageLabel()))
            ->filter()
            ->unique()
            ->values();
    }

    /** @param iterable<int, AcademicCourse> $courses */
    public static function stages(iterable $courses, string $fallback = 'Etapa não informada'): string
    {
        return self::stageLabels($courses)->join(' / ') ?: __($fallback);
    }

    /** @param iterable<int, AcademicCourse> $courses */
    public static function classWithStages(?string $className, iterable $courses): string
    {
        return ($className ?: __('Turma não informada')).' · '.self::stages($courses);
    }

    /** @param iterable<int, AcademicCourse> $courses */
    public static function stageHeading(iterable $courses): string
    {
        return self::stageLabels($courses)->count() > 1 ? __('Etapas') : __('Etapa');
    }
}
