<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class MatrixTechnicalItinerary
{
    /** Ofertas cadastradas com duração compatível; a seleção continua explícita. */
    public function offers(AcademicYear $year, ?AcademicCourse $matrix = null): Collection
    {
        $matrix?->loadMissing('startsPeriod', 'endsPeriod');
        $starts = $matrix?->startsPeriod?->starts_at ?? $year->starts_at;
        $ends = $matrix?->endsPeriod?->ends_at ?? $year->ends_at;
        $people = $matrix?->enrollments()->where('status', '!=', StudentEnrollment::STATUS_CANCELLED)->pluck('person_id')->unique() ?? collect();

        return AcademicCourse::where('stage', AcademicCourse::STAGE_TECHNICAL)
            ->whereHas('academicYear', fn ($q) => $q->where('school_id', $year->school_id)
                ->whereDate('starts_at', '<=', $ends)->whereDate('ends_at', '>=', $starts))
            ->with(['academicYear', 'startsPeriod', 'endsPeriod'])
            ->with(['enrollments' => fn ($q) => $q->whereIn('person_id', $people)->where('status', '!=', StudentEnrollment::STATUS_CANCELLED)])
            ->orderBy('name')->get()
            ->filter(fn ($offer) => ($offer->startsPeriod?->starts_at ?? $offer->academicYear->starts_at)->lte($ends)
                && ($offer->endsPeriod?->ends_at ?? $offer->academicYear->ends_at)->gte($starts))
            ->each(function ($offer) use ($people): void {
                $offer->setAttribute('matrix_students_count', $people->count());
                $offer->setAttribute('matching_students_count', $offer->enrollments->pluck('person_id')->unique()->count());
            })->values();
    }

    public function validate(AcademicYear $year, ?AcademicCourse $matrix, array $ids): void
    {
        $available = $this->offers($year, $matrix)->pluck('id');
        if (collect($ids)->contains(fn ($id) => ! $available->contains((int) $id))) {
            throw ValidationException::withMessages(['technical_itinerary_ids' => __('Selecione um curso técnico da mesma escola com duração compatível com a matriz do Médio.')]);
        }
    }
}
