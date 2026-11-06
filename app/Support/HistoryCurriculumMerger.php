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
            if ($source->isMatrixDerived()) {
                throw ValidationException::withMessages(['catalog_component' => __('Componentes de matrizes do sistema são protegidos e não podem ser mesclados ou alterados pelo catálogo.')]);
            }
            if (! $target->matrixComponents()->exists()) {
                throw ValidationException::withMessages(['target_component_id' => __('O destino deve ser um componente existente em uma matriz do sistema.')]);
            }
            $historyComponents = $source->historyComponents()->orderBy('student_academic_history_components.id')->lockForUpdate()->get();
            $oldRows = [];
            foreach ($historyComponents as $component) {
                $oldRows[] = $component->only(['id', 'name', 'formation', 'knowledge_area']);
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
                'action' => 'updated', 'old_values' => ['merged_component' => $old, 'history_components' => $oldRows],
                'new_values' => $target->only(['id', 'name', 'education_stage', 'formation', 'knowledge_area_id', 'active']),
                'metadata' => ['operation' => 'history-curriculum:merge', 'transferred_links' => $historyComponents->count(), 'record_description' => 'Mesclagem de componente histórico para matriz: '.$old['name'].' → '.$target->name],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);

            return $target;
        });
    }
}
