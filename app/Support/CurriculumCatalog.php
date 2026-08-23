<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\KnowledgeArea;

class CurriculumCatalog
{
    public const FORMATION_FGB = 'Formação Geral Básica';
    public const FORMATION_COMPLEMENTARY = 'Parte Complementar';
    public const FORMATION_ITINERARY = 'Itinerário Formativo';

    /**
     * @return list<string>
     */
    public static function areaNamesForCourse(AcademicCourse $course): array
    {
        $names = collect(config("curriculum.stages.{$course->stage}.formations.formacao_geral_basica.areas", []))
            ->pluck('name')
            ->filter();

        $additionalArea = match ($course->stage) {
            AcademicCourse::STAGE_ELEMENTARY => 'Parte Diversificada',
            AcademicCourse::STAGE_HIGH_SCHOOL => 'Itinerário Formativo',
            AcademicCourse::STAGE_TECHNICAL => 'Educação Profissional e Tecnológica',
            default => null,
        };

        return $names
            ->when($additionalArea, fn ($areas) => $areas->push($additionalArea))
            ->unique()
            ->values()
            ->all();
    }

    public static function knowledgeAreasForCourse(AcademicCourse $course)
    {
        $areaNames = self::areaNamesForCourse($course);

        return KnowledgeArea::query()
            ->where('active', true)
            ->when($areaNames !== [], fn ($query) => $query->whereIn('name', $areaNames))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{component: string, area: string, area_id: int|null}>
     */
    public static function suggestionsForCourse(AcademicCourse $course): array
    {
        $areas = config("curriculum.stages.{$course->stage}.formations.formacao_geral_basica.areas", []);

        if (! is_array($areas) || $areas === []) {
            return [];
        }

        $areaNames = collect($areas)->pluck('name')->filter()->values();
        $areaIds = KnowledgeArea::query()
            ->whereIn('name', $areaNames)
            ->pluck('id', 'name');

        return collect($areas)
            ->flatMap(function (array $area) use ($areaIds): array {
                return collect($area['components'] ?? [])
                    ->map(fn (string $component): array => [
                        'component' => $component,
                        'area' => $area['name'],
                        'area_id' => $areaIds[$area['name']] ?? null,
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }

    public static function areaIdForComponent(AcademicCourse $course, string $componentName): ?int
    {
        $normalizedComponentName = self::normalize($componentName);

        foreach (self::suggestionsForCourse($course) as $suggestion) {
            if (self::normalize($suggestion['component']) === $normalizedComponentName) {
                return $suggestion['area_id'];
            }
        }

        return null;
    }

    public static function formationLabelForArea(AcademicCourse $course, ?KnowledgeArea $area): string
    {
        if ($area === null) {
            return $course->stage === AcademicCourse::STAGE_HIGH_SCHOOL
                ? self::FORMATION_ITINERARY
                : self::FORMATION_COMPLEMENTARY;
        }

        $fgbAreas = collect(config("curriculum.stages.{$course->stage}.formations.formacao_geral_basica.areas", []))
            ->pluck('name')
            ->map(fn (string $name): string => self::normalize($name))
            ->all();

        if (in_array(self::normalize($area->name), $fgbAreas, true)) {
            return self::FORMATION_FGB;
        }

        if ($course->stage === AcademicCourse::STAGE_HIGH_SCHOOL || $course->stage === AcademicCourse::STAGE_TECHNICAL) {
            return self::FORMATION_ITINERARY;
        }

        return self::FORMATION_COMPLEMENTARY;
    }

    public static function areaLabelForComponent(AcademicCourse $course, ?KnowledgeArea $area): string
    {
        if (self::formationLabelForArea($course, $area) !== self::FORMATION_ITINERARY) {
            return $area?->name ?? 'Área não definida';
        }

        if ($course->stage === AcademicCourse::STAGE_TECHNICAL) {
            return $course->name;
        }

        return filled($course->itinerary_name)
            ? $course->itinerary_name
            : 'Aprofundamento de Estudos';
    }

    public static function formationOrder(string $formation): int
    {
        return match ($formation) {
            self::FORMATION_FGB => 10,
            self::FORMATION_ITINERARY => 20,
            self::FORMATION_COMPLEMENTARY => 30,
            default => 90,
        };
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }
}
