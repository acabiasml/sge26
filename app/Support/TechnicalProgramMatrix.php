<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\HistoryCurriculumComponent;
use App\Models\TechnicalProgram;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TechnicalProgramMatrix
{
    public static function nameKey(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/\bii\b/', '2', $name);
        $name = preg_replace('/\bi\b/', '1', $name);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $name));
    }

    public function linkExisting(AcademicCourse $course, TechnicalProgram $program): void
    {
        $definitions = $program->componentDefinitions()->groupBy(fn ($c) => self::nameKey($c['name']));
        foreach ($course->components()->get() as $component) {
            $matches = $definitions->get(self::nameKey($component->name));
            if ($matches?->count() === 1) {
                $component->update(['technical_program_component_key' => $matches->first()['key']]);
            }
        }
    }

    public function synchronize(TechnicalProgram $program): void
    {
        $definitions = $program->componentDefinitions();
        foreach ($program->matrices()->with('components')->get() as $course) {
            foreach ($course->components as $component) {
                if (! $component->technical_program_component_key) {
                    continue;
                }
                $definition = $definitions->get($component->technical_program_component_key);
                if (! $definition) {
                    throw ValidationException::withMessages(['modules' => 'Um componente vinculado a uma matriz não pode ser removido do curso técnico.']);
                }
                $entry = HistoryCurriculumComponent::find($definition['catalog_component_id'] ?? null);
                if (! $entry || $entry->knowledge_area_id !== $program->knowledge_area_id) {
                    throw ValidationException::withMessages(['modules' => 'Selecione os componentes no cadastro curricular da área do curso.']);
                }
                $previous = $component->historyCatalogEntries()->first();
                if ($previous && $previous->id !== $entry->id) {
                    throw ValidationException::withMessages(['modules' => 'Um vínculo já usado por uma matriz não pode ser substituído por outro componente.']);
                }
                $component->fill(['workload_hours' => $definition['hours'], 'weekly_lessons' => null, 'period_loads' => null]);
                $component->adoptCatalogDefinition($entry);
            }
            $course->refreshWorkloadHours();
        }
    }
}
