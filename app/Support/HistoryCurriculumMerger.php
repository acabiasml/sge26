<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HistoryCurriculumMerger
{
    public function supportsMatrixDefinition(HistoryCurriculumComponent $source, HistoryCurriculumComponent $target): bool
    {
        $source->loadMissing('matrixComponents.course');
        $target->loadMissing('area');

        return $source->matrixComponents->every(fn ($matrix) => $matrix->course
            && CurriculumAreaStage::compatible($target->area->name, $matrix->course->stage)
            && $target->formation === ($matrix->course->stage === 'tecnico'
                ? 'Formação Técnica Profissional' : CurriculumCatalog::formationLabelForArea($matrix->course, $target->area)));
    }

    public function merge(int $sourceId, int $targetId, User $actor): HistoryCurriculumComponent
    {
        abort_unless($actor->isAdministrator(), 403);
        if ($sourceId === $targetId) {
            throw ValidationException::withMessages(['target_component_id' => __('Selecione um componente de destino diferente da origem.')]);
        }

        return DB::transaction(function () use ($sourceId, $targetId, $actor): HistoryCurriculumComponent {
            $entries = HistoryCurriculumComponent::whereKey([$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($entries->count() !== 2) {
                throw ValidationException::withMessages(['target_component_id' => __('Um dos componentes mudou ou já foi mesclado. Reabra o catálogo.')]);
            }
            $source = $entries[$sourceId];
            $target = $entries[$targetId]->load('area');
            $matrices = $source->matrixComponents()->with('course')->orderBy('curriculum_components.id')->lockForUpdate()->get();
            $source->setRelation('matrixComponents', $matrices);
            if (! $this->supportsMatrixDefinition($source, $target)) {
                throw ValidationException::withMessages(['target_component_id' => __('A classificação escolhida é incompatível com a etapa de uma das matrizes de origem.')]);
            }
            $historyComponents = $source->historyComponents()->orderBy('student_academic_history_components.id')->lockForUpdate()->get();
            $oldRows = $historyComponents->map->only(['id', 'name', 'formation', 'knowledge_area'])->all();
            $oldMatrices = $matrices->map->only(['id', 'academic_course_id', 'name', 'knowledge_area_id'])->all();
            foreach ($matrices as $matrix) {
                $matrix->adoptCatalogDefinition($target);
            }
            // O salvamento das matrizes já propaga os registros com origem identificada.
            // Completa a migração das transcrições e referências restantes da origem.
            foreach ($source->historyComponents()->lockForUpdate()->get() as $component) {
                $component->adoptCatalogDefinition($target);
                $component->catalogEntries()->sync([$target->id]);
            }
            HistoryCurriculumAlias::where('history_curriculum_component_id', $source->id)->update(['history_curriculum_component_id' => $target->id]);
            HistoryCurriculumAlias::updateOrCreate($source->only(['education_stage', 'formation', 'knowledge_area_id', 'name']), ['history_curriculum_component_id' => $target->id]);
            $old = $source->only(['id', 'name', 'education_stage', 'formation', 'knowledge_area_id', 'active']);
            $source->delete();
            AuditLog::create([
                'actor_user_id' => $actor->id, 'actor_person_id' => $actor->person_id,
                'actor_role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
                'auditable_type' => HistoryCurriculumComponent::class, 'auditable_id' => $target->id,
                'action' => 'updated', 'old_values' => ['merged_component' => $old, 'history_components' => $oldRows, 'matrix_components' => $oldMatrices],
                'new_values' => $target->only(['id', 'name', 'education_stage', 'formation', 'knowledge_area_id', 'active']),
                'metadata' => ['operation' => 'history-curriculum:merge', 'transferred_links' => $historyComponents->count(), 'updated_matrix_components' => $matrices->count(), 'record_description' => 'Mesclagem de componentes: '.$old['name'].' → '.$target->name],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);

            return $target;
        });
    }
}
