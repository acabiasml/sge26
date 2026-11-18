<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\KnowledgeArea;

final class TechnicalProgramAuthorization
{
    public function forArea(KnowledgeArea $area, ?int $schoolId = null, ?AcademicCourse $course = null): ?string
    {
        if (! $area->exists) {
            return $area->getAttributes()['subtitle'] ?? null;
        }
        if ($course?->technicalProgram && $course->technicalProgram->knowledge_area_id === $area->id) {
            return $course->technicalProgram->authorizationReference();
        }
        $linked = $area->relationLoaded('technicalPrograms') ? $area->getRelation('technicalPrograms') : $area->technicalPrograms()->get();
        if ($linked->isEmpty()) {
            return $area->getAttributes()['subtitle'] ?? null;
        }
        $programs = $linked->when($schoolId, fn ($items) => $items->where('school_id', $schoolId));

        return $programs->map(fn ($program) => $program->authorizationReference())->filter()->unique()->join(' · ') ?: null;
    }
}
