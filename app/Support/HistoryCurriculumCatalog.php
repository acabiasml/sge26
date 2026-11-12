<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class HistoryCurriculumCatalog
{
    public const UNCLASSIFIED_STAGE = 'nao_identificada';

    public static function validDefinition(HistoryCurriculumComponent $entry, string $stage): bool
    {
        $entry->loadMissing('area');

        return $entry->area !== null && in_array($stage, ['fundamental', 'medio', 'tecnico'], true) && $entry->education_stage === $stage
            && in_array($entry->formation, self::formations($stage), true)
            && CurriculumAreaStage::compatible($entry->area?->name, $stage);
    }

    public static function validateDefinition(HistoryCurriculumComponent $entry, string $stage, string $field = 'catalog_component_id'): void
    {
        if (! self::validDefinition($entry, $stage)) {
            throw ValidationException::withMessages([$field => __('Selecione um componente do catálogo com etapa, formação e área compatíveis.')]);
        }
    }

    public static function resolveImportedDefinition(string $stage, ?int $areaId, string $name): HistoryCurriculumComponent
    {
        $candidates = HistoryCurriculumComponent::where('education_stage', $stage)->where('knowledge_area_id', $areaId)
            ->with('area')->get()->filter(fn ($entry) => mb_strtolower(trim($entry->name)) === mb_strtolower(trim($name))
                && self::validDefinition($entry, $stage));
        if ($candidates->count() !== 1) {
            throw ValidationException::withMessages(['components' => __('A importação exige um único cadastro compatível no catálogo para o componente: :name.', ['name' => $name])]);
        }

        return $candidates->first();
    }
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
            $config[$stage] = ['formations' => collect(self::formations($stage))->sortBy(fn ($formation) => CurriculumCatalog::alphabeticalKey(__($formation)), SORT_NATURAL)->values()->all(), 'areas' => $areas->filter(fn ($area) => CurriculumAreaStage::compatible($area->name, $stage))->map(fn ($area) => ['id' => $area->id, 'name' => $area->name, 'label' => $area->selectionLabel()])->values()];
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
        foreach (HistoryCurriculumComponent::query()->whereHas('area', fn ($query) => $query->where('active', true))->with('area')->orderBy('formation')->orderBy('name')->get() as $item) {
            if (! array_key_exists($item->education_stage, $catalogs)) {
                continue;
            }
            if (! self::validDefinition($item, $item->education_stage)) {
                continue;
            }
            $catalogs[$item->education_stage][] = ['formation' => $item->formation, 'area' => $item->area->name, 'name' => $item->name]
                + (filled($item->area->subtitle) ? ['area_subtitle' => $item->area->subtitle] : []);
        }

        return $catalogs;
    }

    public static function contains(string $stage, ?string $formation, ?string $area, string $name): bool
    {
        if (! in_array($stage, ['fundamental', 'medio', 'tecnico'], true)
            || ! in_array($formation, self::formations($stage), true) || ! CurriculumAreaStage::compatible($area, $stage)) {
            return false;
        }
        return HistoryCurriculumComponent::where('education_stage', $stage)->where('formation', $formation)->where('name', $name)
            ->whereHas('area', fn ($query) => $query->where('active', true)->where('name', $area))->exists();
    }
}
