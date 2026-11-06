<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use Illuminate\Support\Facades\Schema;

final class HistoryCurriculumCatalog
{
    public const UNCLASSIFIED_STAGE = 'nao_identificada';

    public static function stageLabels(): array
    {
        return AcademicCourse::STAGE_LABELS
            + [self::UNCLASSIFIED_STAGE => 'Etapa não identificada'];
    }

    public static function formations(?string $stage): array
    {
        return $stage === AcademicCourse::STAGE_TECHNICAL ? ['Formação Técnica Profissional'] : array_values(array_filter([
            'Formação Geral Básica', 'Parte Complementar', 'Parte Diversificada',
            $stage === AcademicCourse::STAGE_HIGH_SCHOOL ? 'Itinerário Formativo' : null,
        ]));
    }

    public static function formOptions(): array
    {
        $areas = KnowledgeArea::where('active', true)->orderBy('name')->get()->sortBy(fn ($area) => CurriculumCatalog::alphabeticalKey($area->name), SORT_NATURAL);
        $stages = array_intersect_key(AcademicCourse::STAGE_LABELS, array_flip(['fundamental', 'medio', 'tecnico']));
        $config = [];
        foreach ($stages as $stage => $label) {
            $config[$stage] = ['formations' => collect(self::formations($stage))->sortBy(fn ($formation) => CurriculumCatalog::alphabeticalKey(__($formation)), SORT_NATURAL)->values()->all(), 'areas' => $areas->filter(fn ($area) => CurriculumAreaStage::compatible($area->name, $stage))->map->only(['id', 'name'])->values()];
        }
        $config[''] = ['formations' => [], 'areas' => []];
        $formationLabels = collect(['Formação Geral Básica', 'Parte Complementar', 'Parte Diversificada', 'Itinerário Formativo', 'Formação Técnica Profissional'])->mapWithKeys(fn ($value) => [$value => __($value)])->all();

        return compact('stages', 'config', 'formationLabels');
    }

    public static function all(): array
    {
        $catalogs = array_fill_keys(['fundamental', 'medio', 'tecnico'], []);
        if (! once(fn () => Schema::hasTable('history_curriculum_components'))) {
            return $catalogs;
        }
        foreach (HistoryCurriculumComponent::query()->where('active', true)->whereHas('area', fn ($query) => $query->where('active', true))->with('area')->orderBy('formation')->orderBy('name')->get() as $item) {
            if (! CurriculumAreaStage::compatible($item->area->name, $item->education_stage)) {
                continue;
            }
            $catalogs[$item->education_stage][] = ['formation' => $item->formation, 'area' => $item->area->name, 'name' => $item->name];
        }

        return $catalogs;
    }

    public static function contains(string $stage, ?string $formation, ?string $area, string $name): bool
    {
        return HistoryCurriculumComponent::where('active', true)->where('education_stage', $stage)->where('formation', $formation)->where('name', $name)
            ->whereHas('area', fn ($query) => $query->where('active', true)->where('name', $area))->exists();
    }
}
