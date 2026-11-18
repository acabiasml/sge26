<?php

namespace App\Support;

use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\TechnicalProgram;
use Illuminate\Validation\ValidationException;

final class TechnicalProgramCatalog
{
    public function replaceCatalogReference(HistoryCurriculumComponent $source, HistoryCurriculumComponent $target): void
    {
        foreach (TechnicalProgram::all() as $program) {
            if (! $program->componentDefinitions()->contains('catalog_component_id', $source->id)) {
                continue;
            }
            if ($program->knowledge_area_id !== $target->knowledge_area_id || $target->education_stage !== 'tecnico') {
                throw ValidationException::withMessages(['target_component_id' => 'O componente de destino deve permanecer na área do curso técnico que utiliza esse cadastro.']);
            }
            $modules = json_decode($program->getRawOriginal('modules'), true);
            foreach ($modules as &$module) {
                foreach ($module['components'] as &$component) {
                    if (($component['catalog_component_id'] ?? null) === $source->id) {
                        $component['catalog_component_id'] = $target->id;
                        $component['name'] = $target->name;
                    }
                }
            }
            unset($module, $component);
            $program->update(['modules' => $modules]);
        }
    }

    /** Vincula somente cadastros existentes; não cria componentes, aulas ou resultados. */
    public function backfill(): void
    {
        foreach (TechnicalProgram::with('school')->get() as $program) {
            $school = $program->school;
            $schoolValues = [];
            foreach (['maintainer', 'creation_act', 'accreditation_act', 'education_system'] as $field) {
                if (blank($school->{$field}) && filled($program->getRawOriginal($field))) {
                    $schoolValues[$field] = $program->getRawOriginal($field);
                }
            }
            if ($schoolValues) {
                $school->update($schoolValues);
            }
            $modes = $program->offer_modes;
            if (! $modes) {
                $text = mb_strtolower((string) $program->getRawOriginal('offer_forms'));
                $modes = array_values(array_filter(array_keys(TechnicalProgram::OFFER_MODES), fn ($mode) => str_contains($text, $mode)));
            }
            $area = $program->knowledge_area_id ? $program->area : KnowledgeArea::where('name', $program->graduate_title)->first();
            $modules = json_decode($program->getRawOriginal('modules') ?: '[]', true);
            if ($area) {
                $entries = HistoryCurriculumComponent::where('knowledge_area_id', $area->id)->where('education_stage', 'tecnico')->get()
                    ->groupBy(fn ($entry) => TechnicalProgramMatrix::nameKey($entry->name));
                foreach ($modules as &$module) {
                    foreach ($module['components'] as &$component) {
                        $matches = $entries->get(TechnicalProgramMatrix::nameKey($component['name']));
                        if ($matches?->count() === 1) {
                            $component['catalog_component_id'] = $matches->first()->id;
                        }
                    }
                }
                unset($module, $component);
            }
            if (blank($program->official_gazette_reference) && preg_match('/(?:DOE|D\.O\.)[-\s]*MT.*$/iu', (string) $area?->getRawOriginal('subtitle'), $publication)) {
                $program->official_gazette_reference = $publication[0];
            }
            $program->update(['knowledge_area_id' => $area?->id, 'offer_modes' => $modes, 'modules' => $modules]);
        }
    }
}
