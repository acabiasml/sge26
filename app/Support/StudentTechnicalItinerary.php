<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentTechnicalItinerary
{
    public function independentCourses(Person $person, ?array $schoolIds = null): Collection
    {
        return AcademicCourse::where('stage', AcademicCourse::STAGE_TECHNICAL)
            ->whereHas('enrollments', fn ($q) => $q->where('person_id', $person->id)->where('status', '!=', StudentEnrollment::STATUS_CANCELLED))
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('person_id', $person->id)->whereHas('courses', fn ($q) => $q->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)))
            ->when($schoolIds !== null, fn ($q) => $q->whereHas('academicYear', fn ($q) => $q->whereIn('school_id', $schoolIds)))
            ->whereDoesntHave('itineraryEnrollments', fn ($q) => $q->where('person_id', $person->id))
            ->with('academicYear.school')->orderBy('name')->get();
    }

    public function options(StudentEnrollment $enrollment): Collection
    {
        $matrixIds = $enrollment->courses()->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)->pluck('academic_courses.id');

        return AcademicCourse::where('stage', AcademicCourse::STAGE_TECHNICAL)
            ->whereHas('enrollments', fn ($q) => $q->where('person_id', $enrollment->person_id)->where('status', '!=', StudentEnrollment::STATUS_CANCELLED))
            ->whereHas('academicYear', fn ($q) => $q->where('school_id', $enrollment->schoolClass->academicYear->school_id))
            ->whereIn('id', DB::table('academic_course_technical_itinerary')->whereIn('academic_course_id', $matrixIds)->select('technical_course_id'))
            ->with('academicYear')->orderBy('name')->get();
    }

    public function assign(StudentEnrollment $enrollment, ?int $courseId): void
    {
        DB::transaction(function () use ($enrollment, $courseId): void {
            $enrollment = StudentEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if (! $enrollment->courses()->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)->exists()
                || ($courseId && ! $this->options($enrollment)->contains('id', $courseId))) {
                throw ValidationException::withMessages(['technical_itinerary_course_id' => __('Selecione uma oferta técnica prevista na matriz do Médio e com matrícula deste estudante.')]);
            }
            if (! $courseId && ! $enrollment->courses()->where('stage', AcademicCourse::STAGE_TECHNICAL)->exists()
                && ! $enrollment->courses()->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)->whereNotNull('itinerary_name')->where('itinerary_name', '!=', '')->exists()) {
                throw ValidationException::withMessages(['technical_itinerary_course_id' => __('Esta matriz não possui itinerário próprio. Selecione a oferta técnica do estudante.')]);
            }
            $enrollment->update(['technical_itinerary_course_id' => $courseId]);
        });
    }

    public function canIssue(StudentAcademicHistory $history): bool
    {
        if ($history->education_stage !== AcademicCourse::STAGE_TECHNICAL
            && app(StudentHistoryConsolidator::class)->stage($history) !== AcademicCourse::STAGE_TECHNICAL) {
            return true;
        }

        return $history->technical_course_id !== null
            && $this->independentCourses($history->student)->contains('id', $history->technical_course_id);
    }
}
