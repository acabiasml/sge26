<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\CurriculumComponent;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class StudentEnrollmentContinuity
{
    /** @return Collection<int, StudentEnrollment> */
    public function previous(StudentEnrollment $enrollment): Collection
    {
        $enrollment->loadMissing('schoolClass.academicYear', 'courses');
        $year = $enrollment->schoolClass->academicYear;
        $until = $enrollment->enrolled_at?->toDateString() ?? $year->ends_at->toDateString();
        $courseKeys = $enrollment->courses->map(fn (AcademicCourse $course): string => $this->courseKey($course))->all();

        return StudentEnrollment::query()
            ->where('person_id', $enrollment->person_id)
            ->where('id', '!=', $enrollment->id)
            ->where('status', StudentEnrollment::STATUS_TRANSFERRED)
            ->whereDate('enrolled_at', '<=', $until)
            ->whereDate('transferred_at', '<=', $until)
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('reference_year', $year->reference_year))
            ->with(['schoolClass.academicYear', 'courses'])
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (StudentEnrollment $source): bool => $source->courses
                ->contains(fn (AcademicCourse $course): bool => in_array($this->courseKey($course), $courseKeys, true)));
    }

    public function courseKey(AcademicCourse $course): string
    {
        return $course->stage.'|'.Str::lower(Str::ascii(trim($course->name)));
    }

    public function componentKey(CurriculumComponent $component): string
    {
        return $this->courseKey($component->course).'|'.Str::lower(Str::ascii(trim($component->name)));
    }
}
