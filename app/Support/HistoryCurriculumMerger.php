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
    public function merge(int $firstId, int $secondId, int $keepId, User $actor): HistoryCurriculumComponent
    {
        abort_unless($actor->isAdministrator(), 403);
        if ($firstId === $secondId || ! in_array($keepId, [$firstId, $secondId], true)) {
            throw ValidationException::withMessages(['other_component_id' => __('Selecione dois componentes diferentes e a nomenclatura que deseja manter.')]);
        }

        return DB::transaction(function () use ($firstId, $secondId, $keepId, $actor): HistoryCurriculumComponent {
            $entries = HistoryCurriculumComponent::whereKey([$firstId, $secondId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($entries->count() !== 2) {
                throw ValidationException::withMessages(['other_component_id' => __('Um dos componentes mudou ou já foi mesclado. Reabra o catálogo.')]);
            }
            $keep = $entries[$keepId];
            $remove = $entries[$keepId === $firstId ? $secondId : $firstId];
            if ($keep->only(['education_stage', 'formation', 'knowledge_area_id']) !== $remove->only(['education_stage', 'formation', 'knowledge_area_id'])) {
                throw ValidationException::withMessages(['other_component_id' => __('A mesclagem exige a mesma etapa, formação e área curricular.')]);
            }
            $links = DB::table('history_catalog_usages')->where('history_curriculum_component_id', $remove->id)->orderBy('student_academic_history_component_id')->lockForUpdate()->get();
            foreach ($links as $link) {
                DB::table('history_catalog_usages')->insertOrIgnore([
                    'history_curriculum_component_id' => $keep->id,
                    'student_academic_history_component_id' => $link->student_academic_history_component_id,
                ]);
            }
            DB::table('history_catalog_usages')->where('history_curriculum_component_id', $remove->id)->delete();
            HistoryCurriculumAlias::where('history_curriculum_component_id', $remove->id)->update(['history_curriculum_component_id' => $keep->id]);
            HistoryCurriculumAlias::updateOrCreate($remove->only(['education_stage', 'formation', 'knowledge_area_id', 'name']), ['history_curriculum_component_id' => $keep->id]);
            $old = $remove->only(['id', 'name', 'education_stage', 'formation', 'knowledge_area_id', 'active']);
            $keep->update(['active' => $keep->active || $remove->active]);
            $remove->delete();
            AuditLog::create([
                'actor_user_id' => $actor->id, 'actor_person_id' => $actor->person_id,
                'actor_role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
                'auditable_type' => HistoryCurriculumComponent::class, 'auditable_id' => $keep->id,
                'action' => 'updated', 'old_values' => ['merged_component' => $old],
                'new_values' => $keep->only(['id', 'name', 'education_stage', 'formation', 'knowledge_area_id', 'active']),
                'metadata' => ['operation' => 'history-curriculum:merge', 'transferred_links' => $links->count(), 'record_description' => 'Mesclagem de componentes do catálogo: '.$old['name'].' → '.$keep->name],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);

            return $keep;
        });
    }
}
