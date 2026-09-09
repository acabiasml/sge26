<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;

final class SchoolClassEnrollmentCourses
{
    public static function synchronize(SchoolClass $class): void
    {
        if (! $class->active || ! $class->academicYear?->active || $class->academicYear->isClosed()) {
            return;
        }

        $courseIds = $class->courses()->pluck('academic_courses.id')->all();

        $class->enrollments()
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->where(fn ($query) => $query->whereNull('final_result_status')
                ->orWhere('final_result_status', StudentEnrollment::FINAL_PENDING))
            ->each(function (StudentEnrollment $enrollment) use ($courseIds): void {
                // Added class matrices apply to current students. Older links remain
                // available for grades and documents already recorded.
                $enrollment->courses()->syncWithoutDetaching($courseIds);
            });
    }
}
