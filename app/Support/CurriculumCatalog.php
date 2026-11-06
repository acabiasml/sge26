<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use Illuminate\Support\Str;

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

    public static function knowledgeAreasForCourse(AcademicCourse $course, ?KnowledgeArea $currentArea = null)
    {
        return KnowledgeArea::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->when($currentArea, fn ($areas) => $areas->push($currentArea))
            ->filter(fn (KnowledgeArea $area) => CurriculumAreaStage::compatible($area->name, $course->stage))
            ->unique('id')
            ->sortBy(fn (KnowledgeArea $area) => self::alphabeticalKey($area->name), SORT_NATURAL)
            ->values();
    }

    /**
     * @return array<int, array{component: string, area: string, area_id: int|null}>
     */
    public static function suggestionsForCourse(AcademicCourse $course, ?CurriculumComponent $currentComponent = null): array
    {
        $currentIds = $currentComponent?->historyCatalogEntries()->pluck('history_curriculum_components.id')->all() ?? [];

        return HistoryCurriculumComponent::query()
            ->where('education_stage', $course->stage)
            ->where(function ($query) use ($currentIds): void {
                $query->whereHas('area', fn ($area) => $area->where('active', true))
                    ->orWhereIn('id', $currentIds);
            })
            ->with('area')->orderBy('name')->get()
            ->filter(fn ($entry) => CurriculumAreaStage::compatible($entry->area->name, $course->stage))
            ->map(fn ($entry) => ['component' => $entry->name, 'area' => $entry->area->name, 'area_id' => $entry->knowledge_area_id])
            ->unique('component')
            ->sortBy(fn (array $item) => self::alphabeticalKey($item['component']), SORT_NATURAL)
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
        return $area?->formation ?: 'Formação não definida';
    }

    public static function areaLabelForComponent(AcademicCourse $course, ?KnowledgeArea $area): string
    {
        return $area?->name ?? 'Área não definida';
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

    public static function alphabeticalKey(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }
}
