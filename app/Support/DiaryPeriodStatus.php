<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryPeriodConfirmation;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DiaryPeriodStatus
{
    /** @return Collection<int, SchoolClassComponent> */
    public function assignments(AcademicYear $academicYear, bool $includeInactive = false): Collection
    {
        return SchoolClassComponent::query()
            ->with(['schoolClass.courses', 'schoolClass.startsPeriod', 'schoolClass.endsPeriod', 'component.area', 'component.course', 'component.startsPeriod', 'component.endsPeriod', 'teacher'])
            ->when(! $includeInactive, fn (Builder $query) => $query->where('active', true))
            ->whereHas('schoolClass', fn (Builder $query) => $query->where('academic_year_id', $academicYear->id)->when(! $includeInactive, fn (Builder $classes) => $classes->where('active', true)))
            ->whereHas('component.course', fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
            ->get()
            ->filter(fn (SchoolClassComponent $assignment): bool => $assignment->schoolClass?->courses->contains('id', $assignment->component?->course?->id) ?? false)
            ->values();
    }

    /**
     * @return array{attendance_without_content:list<string>,content_without_attendance:list<string>,missing_grades:int,is_pending:bool}
     */
    public function pending(SchoolClassComponent $assignment, AcademicPeriod $period): array
    {
        $enrollments = $assignment->schoolClass->enrollments()
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->get();
        $assessments = DiaryAssessment::query()
            ->with('results')
            ->where('school_class_id', $assignment->school_class_id)
            ->where('curriculum_component_id', $assignment->curriculum_component_id)
            ->where('academic_period_id', $period->id)
            ->where(fn (Builder $query) => $query->whereNotNull('school_assessment_rule_id')->orWhere('is_recovery', true))
            ->get();
        $attendance = DiaryAttendanceRecord::query()
            ->where('school_class_id', $assignment->school_class_id)
            ->where('curriculum_component_id', $assignment->curriculum_component_id)
            ->where('academic_period_id', $period->id)
            ->get();
        $contents = DiaryContent::query()
            ->where('school_class_id', $assignment->school_class_id)
            ->where('curriculum_component_id', $assignment->curriculum_component_id)
            ->where('academic_period_id', $period->id)
            ->get();

        $attendanceDates = $attendance->map(fn (DiaryAttendanceRecord $record): string => $record->class_date->toDateString())->unique()->sort()->values();
        $contentDates = $contents->map(fn (DiaryContent $content): string => $content->class_date->toDateString())->unique()->sort()->values();
        $missingGrades = $this->missingGrades($enrollments, $assessments);
        $pending = [
            'attendance_without_content' => array_values(array_diff($attendanceDates->all(), $contentDates->all())),
            'content_without_attendance' => array_values(array_diff($contentDates->all(), $attendanceDates->all())),
            'missing_grades' => $missingGrades,
        ];
        $pending['is_pending'] = $pending['attendance_without_content'] !== []
            || $pending['content_without_attendance'] !== []
            || $pending['missing_grades'] > 0;

        return $pending;
    }

    /**
     * @param  Collection<int, StudentEnrollment>  $enrollments
     * @param  Collection<int, DiaryAssessment>  $assessments
     */
    public function missingGrades(Collection $enrollments, Collection $assessments): int
    {
        $regularAssessments = $assessments->where('is_recovery', false);
        if ($enrollments->isEmpty() || $regularAssessments->isEmpty()) {
            return 0;
        }

        // A ficha de origem registra a nota do período, sem criar avaliações fictícias no diário.
        $originGrades = StudentPeriodConvalidation::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->whereIn('academic_period_id', $regularAssessments->pluck('academic_period_id')->unique())
            ->whereIn('curriculum_component_id', $regularAssessments->pluck('curriculum_component_id')->unique())
            ->whereNotNull('score')
            ->get(['student_enrollment_id', 'academic_period_id', 'curriculum_component_id'])
            ->keyBy(fn (StudentPeriodConvalidation $record): string => $record->student_enrollment_id.'-'.$record->academic_period_id.'-'.$record->curriculum_component_id);

        return $regularAssessments->sum(function (DiaryAssessment $assessment) use ($enrollments, $originGrades): int {
            return $enrollments->filter(fn (StudentEnrollment $enrollment): bool => ! $originGrades->has($enrollment->id.'-'.$assessment->academic_period_id.'-'.$assessment->curriculum_component_id)
                && $assessment->results->firstWhere('student_enrollment_id', $enrollment->id)?->score === null
            )->count();
        });
    }

    /** @return Collection<int, array{assignment:SchoolClassComponent,confirmation:?DiaryPeriodConfirmation,pending:array{attendance_without_content:list<string>,content_without_attendance:list<string>,missing_grades:int,is_pending:bool}}> */
    public function summaries(AcademicYear $academicYear, AcademicPeriod $period, bool $includeInactive = false): Collection
    {
        $confirmations = DiaryPeriodConfirmation::query()
            ->where('academic_period_id', $period->id)
            ->get()
            ->keyBy(fn (DiaryPeriodConfirmation $confirmation): string => $confirmation->school_class_id.'-'.$confirmation->curriculum_component_id);

        return $this->assignments($academicYear, $includeInactive)
            ->filter(fn (SchoolClassComponent $assignment): bool => $this->assignmentIsActiveInPeriod($assignment, $period))
            ->map(function (SchoolClassComponent $assignment) use ($period, $confirmations): array {
                return [
                    'assignment' => $assignment,
                    'confirmation' => $confirmations->get($assignment->school_class_id.'-'.$assignment->curriculum_component_id),
                    'pending' => $this->pending($assignment, $period),
                ];
            })
            ->sortBy([
                [fn (array $summary): string => $summary['assignment']->schoolClass?->name ?? '', 'asc'],
                [fn (array $summary): string => $summary['assignment']->component?->name ?? '', 'asc'],
            ])
            ->values();
    }

    private function assignmentIsActiveInPeriod(SchoolClassComponent $assignment, AcademicPeriod $period): bool
    {
        $componentActive = $assignment->component?->isActiveInPeriod($period) ?? false;

        if (! $componentActive) {
            return false;
        }

        $class = $assignment->schoolClass;

        if ($class?->startsPeriod && $period->position < $class->startsPeriod->position) {
            return false;
        }

        if ($class?->endsPeriod && $period->position > $class->endsPeriod->position) {
            return false;
        }

        return true;
    }
}
