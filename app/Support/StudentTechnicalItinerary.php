<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;

final class StudentTechnicalItinerary
{
    public function independentCourses(Person $person, ?array $schoolIds = null): Collection
    {
        return AcademicCourse::where('stage', AcademicCourse::STAGE_TECHNICAL)
            ->whereHas('enrollments', fn ($q) => $q->where('person_id', $person->id)->where('status', '!=', StudentEnrollment::STATUS_CANCELLED))
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('person_id', $person->id)->whereHas('courses', fn ($q) => $q->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)))
            ->when($schoolIds !== null, fn ($q) => $q->whereHas('academicYear', fn ($q) => $q->whereIn('school_id', $schoolIds)))
            ->whereDoesntHave('itineraryMatrices', fn ($q) => $q->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)
                ->whereHas('enrollments', fn ($q) => $q->where('person_id', $person->id)))
            ->with('academicYear.school')->orderBy('name')->get();
    }

    /** O vínculo é definido na matriz e vale para todas as suas matrículas. */
    public function courseIdsForEnrollment(StudentEnrollment $enrollment): Collection
    {
        return AcademicCourse::where('stage', AcademicCourse::STAGE_TECHNICAL)
            ->whereHas('itineraryMatrices', fn ($q) => $q->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL)
                ->whereHas('enrollments', fn ($q) => $q->whereKey($enrollment->id)))
            ->pluck('id');
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
