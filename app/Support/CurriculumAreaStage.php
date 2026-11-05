<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\KnowledgeArea;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CurriculumAreaStage
{
    /** Equivalências entre áreas, sem inferir a área pelo nome do componente. */
    public static function canonicalName(?string $name, ?string $stage): ?string
    {
        if (! in_array($stage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL], true) || blank($name)) {
            return $name;
        }
        $base = preg_replace('/(?:, codigos)? e (?:suas )?tecnologias$| e sociais aplicadas$/', '', self::normalize($name));
        $elementary = config('curriculum.stages.fundamental.formations.formacao_geral_basica.areas');
        $secondary = config('curriculum.stages.medio.formations.formacao_geral_basica.areas');
        foreach (array_slice($elementary, 0, 4) as $index => $area) {
            if ($base === self::normalize($area['name'])) {
                return $stage === AcademicCourse::STAGE_ELEMENTARY ? $area['name'] : $secondary[$index]['name'];
            }
        }

        return $name;
    }

    public static function compatible(?string $name, ?string $stage): bool
    {
        if (blank($name) || ! in_array($stage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL], true)) {
            return true;
        }
        $normalized = self::normalize($name);
        if (self::normalize(self::canonicalName($name, $stage)) !== $normalized) {
            return false;
        }
        if ($stage === AcademicCourse::STAGE_ELEMENTARY) {
            return ! preg_match('/tecnologias|aplicadas/', $normalized)
                && $normalized !== 'itinerario formativo';
        }

        return ! in_array($normalized, ['ensino religioso', 'parte diversificada', 'parte complementar'], true);
    }

    public static function validate(?string $name, ?string $stage, string $field): void
    {
        if (! self::compatible($name, $stage)) {
            throw ValidationException::withMessages([$field => __('Selecione uma área de conhecimento compatível com a etapa de ensino.')]);
        }
    }

    public static function stageFromLabel(?string $label): ?string
    {
        $label = self::normalize($label);

        return match (true) {
            str_contains($label, 'tecnic') => AcademicCourse::STAGE_TECHNICAL,
            str_contains($label, 'fundamental') => AcademicCourse::STAGE_ELEMENTARY,
            $label === 'medio' || str_contains($label, 'ensino medio') => AcademicCourse::STAGE_HIGH_SCHOOL,
            default => null,
        };
    }

    public static function historyStage(StudentAcademicHistory $history): ?string
    {
        if (filled($history->education_stage)) {
            return $history->education_stage;
        }
        $history->loadMissing('years');
        $stages = $history->years->pluck('stage')->filter()->map(fn ($label) => self::stageFromLabel($label))->unique();
        if ($stages->isNotEmpty()) {
            return $stages->count() === 1 ? $stages->first() : null;
        }

        return self::stageFromLabel($history->stage);
    }

    /** Normaliza associações de importações; não altera o catálogo compartilhado. */
    public static function areaForStage(?KnowledgeArea $area, ?string $stage): ?KnowledgeArea
    {
        if (! $area || self::compatible($area->name, $stage)) {
            return $area;
        }
        $name = self::canonicalName($area->name, $stage);
        self::validate($name, $stage, 'knowledge_area_id');

        return KnowledgeArea::firstOrCreate(['name' => $name], [
            'formation' => $area->formation ?: CurriculumCatalog::FORMATION_FGB,
            'sort_order' => $area->sort_order, 'active' => true,
        ]);
    }

    private static function normalize(?string $value): string
    {
        return Str::lower(Str::ascii(trim(preg_replace('/\s+/u', ' ', $value ?? ''))));
    }
}
