<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Espelha definições das matrizes sem modificar seus registros de origem. */
final class MatrixHistoryCatalog
{
    public function importAll(): int
    {
        $count = 0;
        foreach (CurriculumComponent::with(['course', 'area'])->lazyById(200) as $component) {
            if ($this->syncComponent($component)) {
                $count++;
            }
        }
        HistoryCurriculumComponent::whereHas('historyComponents.records.year', fn ($query) => $query->where('source', 'system'))
            ->update(['matrix_origin' => true]);

        return $count;
    }

    public function syncComponent(CurriculumComponent $component): ?HistoryCurriculumComponent
    {
        if (! Schema::hasTable('matrix_catalog_components')) {
            return null;
        }
        // Inclui os defaults efetivamente gravados no banco sem modificar o objeto
        // que está sendo salvo pelo fluxo de cadastro da matriz.
        $component = $component->fresh(['course', 'area']);
        if (! $component || ! $course = $component->course) {
            return null;
        }

        return DB::transaction(function () use ($component, $course): HistoryCurriculumComponent {
            $formation = $course->stage === AcademicCourse::STAGE_TECHNICAL ? 'Formação Técnica Profissional' : CurriculumCatalog::formationLabelForArea($course, $component->area);
            $area = $component->area ?: KnowledgeArea::firstOrCreate(['name' => TextNormalizer::titleCase('Área não definida')], ['formation' => $formation, 'active' => true]);
            $stage = array_key_exists($course->stage, AcademicCourse::STAGE_LABELS) ? $course->stage : HistoryCurriculumCatalog::UNCLASSIFIED_STAGE;
            $context = ['knowledge_area_id' => $area->id, 'education_stage' => $stage, 'formation' => $formation];
            $findEntry = fn () => HistoryCurriculumComponent::where($context)->get()->first(fn ($entry) => Str::lower(trim($entry->name)) === Str::lower(trim($component->name)));
            $entry = $findEntry();
            if (! $entry) {
                // A grafia é uma cópia fiel da matriz, inclusive em importações que
                // não passaram pela normalização de texto dos formulários.
                DB::table('history_curriculum_components')->insertOrIgnore($context + [
                    'name' => $component->name, 'active' => (bool) $component->active, 'matrix_origin' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $entry = $findEntry();
                abort_unless($entry, 422);
            }
            $active = $component->active || $entry->matrixComponents()->where('curriculum_components.id', '!=', $component->id)->where('curriculum_components.active', true)->exists();
            $definition = $context + ['name' => $component->name, 'matrix_origin' => true, 'active' => $active];
            if ($entry->only(array_keys($definition)) != $definition) {
                DB::table('history_curriculum_components')->where('id', $entry->id)->update($definition + ['updated_at' => now()]);
            }
            DB::table('matrix_catalog_components')->updateOrInsert(['curriculum_component_id' => $component->id], ['history_curriculum_component_id' => $entry->id]);

            return $entry->fresh();
        });
    }
}
