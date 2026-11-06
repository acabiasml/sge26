<?php

namespace App\Support;

use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Integra o cadastro sem alterar as transcrições e os resultados dos estudantes. */
final class HistoryCatalogIntegrator
{
    private ?array $areas = null;

    private ?array $entries = null;

    private ?array $aliases = null;

    public function importAll(): int
    {
        $count = 0;
        foreach (StudentAcademicHistoryComponent::with(['history.years', 'records.year', 'catalogEntries'])->lazyById(200) as $component) {
            $this->syncComponent($component);
            $count++;
        }

        return $count;
    }

    public function syncHistory(StudentAcademicHistory $history): void
    {
        if (! Schema::hasTable('history_catalog_usages')) {
            return;
        }
        $history->loadMissing('years');
        foreach ($history->components()->with(['records.year', 'catalogEntries'])->get() as $component) {
            $component->setRelation('history', $history);
            $this->syncComponent($component);
        }
    }

    private function syncComponent(StudentAcademicHistoryComponent $component): void
    {
        // As referências a matrizes, inclusive destinos escolhidos por mesclagem,
        // prevalecem sobre a etapa anteriormente transcrita no histórico.
        $matrixEntries = $component->catalogEntries->filter(fn ($entry) => $entry->matrix_origin);
        if ($matrixEntries->isNotEmpty()) {
            return;
        }
        $history = $component->history;
        $allowedStages = ['fundamental', 'medio', 'tecnico'];
        $fixedStage = in_array($history?->education_stage, $allowedStages, true) ? $history->education_stage : null;
        $fallbackStage = $history ? CurriculumAreaStage::historyStage($history) : null;
        $fallbackStage = in_array($fallbackStage, $allowedStages, true) ? $fallbackStage : null;
        $stages = $component->records->map(fn ($record) => $fixedStage ?: (CurriculumAreaStage::stageFromLabel($record->year?->stage) ?: $fallbackStage))
            ->filter()->unique()->values();
        if ($stages->isEmpty()) {
            $stages->push($fixedStage ?: $fallbackStage ?: HistoryCurriculumCatalog::UNCLASSIFIED_STAGE);
        }
        $ids = [];
        foreach ($stages as $stage) {
            $linked = $stage === HistoryCurriculumCatalog::UNCLASSIFIED_STAGE
                ? $component->catalogEntries : $component->catalogEntries->where('education_stage', $stage);
            if ($linked->isNotEmpty()) {
                $ids = array_merge($ids, $linked->modelKeys());

                continue;
            }
            $areaName = CurriculumAreaStage::canonicalName($component->knowledge_area, $stage) ?: 'Área não informada';
            $formation = $component->formation ?: 'Formação não informada';
            $area = $this->area($areaName, $formation);
            $key = $this->key([$stage, $formation, $area->id, $component->name]);
            $this->entries ??= HistoryCurriculumComponent::all()->keyBy(fn ($entry) => $this->key([$entry->education_stage, $entry->formation, $entry->knowledge_area_id, $entry->name]))->all();
            if (($this->entries[$key] ?? null)?->matrix_origin) {
                $ids[] = $this->entries[$key]->id;

                continue;
            }
            $this->aliases ??= Schema::hasTable('history_curriculum_aliases')
                ? HistoryCurriculumAlias::with('target.area')->get()->keyBy(fn ($alias) => $this->key([$alias->education_stage, $alias->formation, $alias->knowledge_area_id, $alias->name]))->all() : [];
            $hasSystemRecords = $component->records->contains(fn ($record) => $record->year?->source === 'system');
            if (! $hasSystemRecords && ($alias = $this->aliases[$key] ?? null)) {
                $ids[] = $alias->history_curriculum_component_id;
                if ($alias->target->matrix_origin) {
                    $component->adoptCatalogDefinition($alias->target);
                }

                continue;
            }
            $entry = $this->entries[$key] ??= HistoryCurriculumComponent::firstOrCreate([
                'education_stage' => $stage, 'formation' => $formation,
                'knowledge_area_id' => $area->id, 'name' => $component->name,
            ], [
                'active' => $stage !== HistoryCurriculumCatalog::UNCLASSIFIED_STAGE && $area->active
                    && filled($component->knowledge_area) && CurriculumAreaStage::compatible($area->name, $stage)
                    && in_array($formation, HistoryCurriculumCatalog::formations($stage), true),
            ]);
            $ids[] = $entry->id;
        }
        $component->catalogEntries()->sync($ids);
    }

    private function area(string $name, string $formation): KnowledgeArea
    {
        $this->areas ??= KnowledgeArea::all()->keyBy(fn ($area) => $this->key([$area->name]))->all();
        $key = $this->key([$name]);

        return $this->areas[$key] ??= KnowledgeArea::firstOrCreate(['name' => $name], ['formation' => $formation, 'active' => true]);
    }

    private function key(array $parts): string
    {
        return json_encode(array_map(fn ($value) => Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $value))), $parts), JSON_THROW_ON_ERROR);
    }
}
