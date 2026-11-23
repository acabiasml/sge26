<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\StudentEnrollment;

final class HighSchoolCompletion
{
    public function course(StudentEnrollment $enrollment): ?AcademicCourse
    {
        if ($enrollment->final_result_status !== StudentEnrollment::FINAL_APPROVED
            || ! $enrollment->schoolClass->academicYear->isClosed()
            || in_array($enrollment->status, [StudentEnrollment::STATUS_CANCELLED, StudentEnrollment::STATUS_TRANSFERRED, StudentEnrollment::STATUS_RECLASSIFIED], true)) {
            return null;
        }
        $enrollment->loadMissing('courses');

        return $enrollment->courses->first(fn (AcademicCourse $course) => $course->stage === AcademicCourse::STAGE_HIGH_SCHOOL
            && app(StudentAcademicHistoryCompleteness::class)->gradeNumber($course->name, $course->stage) === 3);
    }
}
