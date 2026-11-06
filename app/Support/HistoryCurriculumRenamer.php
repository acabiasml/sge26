<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\PersonSchoolRole;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HistoryCurriculumRenamer
{
    public function rename(int $componentId, string $name, User $actor): HistoryCurriculumComponent
    {
        abort_unless($actor->isAdministrator(), 403);

        return DB::transaction(function () use ($componentId, $name, $actor): HistoryCurriculumComponent {
            $entry = HistoryCurriculumComponent::whereKey($componentId)->lockForUpdate()->firstOrFail();
            $context = $entry->only(['education_stage', 'formation', 'knowledge_area_id']);
            $name = trim(preg_replace('/\s+/u', ' ', $name));
            Validator::make(['name' => $name], [
                'name' => ['required', 'string', 'max:255', Rule::unique('history_curriculum_components')->where(fn ($query) => $query->where($context))->ignore($entry->id)],
            ])->validate();
            if (HistoryCurriculumAlias::where($context)->where('name', $name)
                ->where('history_curriculum_component_id', '!=', $entry->id)->exists()) {
                throw ValidationException::withMessages(['name' => __('Esta nomenclatura já foi incorporada a outro componente do catálogo.')]);
            }
            if ($entry->name === $name) {
                return $entry;
            }

            $matrices = $entry->matrixComponents()->orderBy('curriculum_components.id')->lockForUpdate()->get();
            $histories = $entry->historyComponents()->orderBy('student_academic_history_components.id')->lockForUpdate()->get();
            $oldName = $entry->name;
            $labels = ['name' => $name, 'updated_at' => now()];
            // Atualiza apenas o nome. Os eventos de classificação completa poderiam
            // recriar referências ou alterar formação/área de transcrições antigas.
            HistoryCurriculumComponent::whereKey($entry->id)->update($labels);
            CurriculumComponent::whereKey($matrices->modelKeys())->update($labels);
            StudentAcademicHistoryComponent::whereKey($histories->modelKeys())->update($labels);
            // Mantém a nomenclatura anterior reconhecível, sem tomar um alias alheio.
            HistoryCurriculumAlias::firstOrCreate($context + ['name' => $oldName], ['history_curriculum_component_id' => $entry->id]);
            AuditLog::create([
                'actor_user_id' => $actor->id, 'actor_person_id' => $actor->person_id,
                'actor_role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
                'auditable_type' => HistoryCurriculumComponent::class, 'auditable_id' => $entry->id,
                'action' => 'updated',
                'old_values' => ['name' => $oldName, 'matrix_components' => $matrices->map->only(['id', 'name'])->all(), 'history_components' => $histories->map->only(['id', 'name'])->all()],
                'new_values' => ['name' => $name],
                'metadata' => ['operation' => 'history-curriculum:rename', 'updated_matrix_components' => $matrices->count(), 'updated_history_components' => $histories->count(), 'record_description' => 'Alteração do nome do componente: '.$oldName.' → '.$name],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);

            return $entry->fresh();
        });
    }
}
