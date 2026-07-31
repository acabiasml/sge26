<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class StudentAttendanceCertificateBuilder
{
    public function __construct(private readonly AttendanceSummaryCalculator $attendanceCalculator) {}

    /**
     * @return array{
     *     attendance: array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null},
     *     matrices: Collection<int, array{course: AcademicCourse, stage: string, attendance: array<string, int|float|null>}>
     * }
     */
    public function build(
        StudentEnrollment $enrollment,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?AcademicPeriod $period = null,
    ): array {
        $enrollment->loadMissing('courses.components');
        $courses = $enrollment->courses->sortBy('name')->values();
        $componentIds = $courses
            ->flatMap(fn (AcademicCourse $course) => $course->components->where('active', true)->pluck('id'))
            ->unique()
            ->values();

        $recordStartsAt = max(
            $startsAt->toDateString(),
            $enrollment->enrolled_at?->toDateString() ?? $startsAt->toDateString(),
        );
        $recordEndsAt = min(array_filter([
            $endsAt->toDateString(),
            $enrollment->transferred_at?->toDateString(),
            $enrollment->cancelled_at?->toDateString(),
        ]));

        $records = DiaryAttendanceRecord::query()
            ->with(['entries' => fn ($query) => $query->where('student_enrollment_id', $enrollment->id)])
            ->where('school_class_id', $enrollment->school_class_id)
            ->whereIn('curriculum_component_id', $componentIds)
            ->whereHas('entries', fn ($query) => $query->where('student_enrollment_id', $enrollment->id))
            ->when(
                $recordStartsAt <= $recordEndsAt,
                fn ($query) => $query
                    ->whereDate('class_date', '>=', $recordStartsAt)
                    ->whereDate('class_date', '<=', $recordEndsAt),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->when($period, fn ($query) => $query->where('academic_period_id', $period->id))
            ->orderBy('class_date')
            ->get();

        $justifications = DiaryAttendanceJustification::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereDate('starts_at', '<=', $endsAt->toDateString())
            ->whereDate('ends_at', '>=', $startsAt->toDateString())
            ->get();

        $convalidations = StudentPeriodConvalidation::query()
            ->with('period')
            ->where('student_enrollment_id', $enrollment->id)
            ->whereIn('curriculum_component_id', $componentIds)
            ->whereNotNull('attendance_lessons')
            ->when(
                $period,
                fn ($query) => $query->where('academic_period_id', $period->id),
                fn ($query) => $query->whereHas('period', fn ($periods) => $periods
                    ->whereDate('starts_at', '>=', $startsAt->toDateString())
                    ->whereDate('ends_at', '<=', $endsAt->toDateString())),
            )
            ->get();

        $matrices = $courses->map(function (AcademicCourse $course) use ($records, $justifications, $convalidations): array {
            $componentIds = $course->components->where('active', true)->pluck('id');
            $attendance = $this->attendanceCalculator->summarize(
                $records->whereIn('curriculum_component_id', $componentIds)->values(),
                $justifications,
            );

            return [
                'course' => $course,
                'stage' => $course->stageLabel(),
                'attendance' => $this->attendanceCalculator->aggregate(collect([
                    $attendance,
                    $this->externalAttendance($convalidations->whereIn('curriculum_component_id', $componentIds)->values()),
                ])),
            ];
        })->values();

        return [
            'attendance' => $this->attendanceCalculator->aggregate(collect([
                $this->attendanceCalculator->summarize($records, $justifications),
                $this->externalAttendance($convalidations),
            ])),
            'matrices' => $matrices,
        ];
    }

    /**
     * @param  Collection<int, StudentPeriodConvalidation>  $convalidations
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null}
     */
    private function externalAttendance(Collection $convalidations): array
    {
        $lessons = $convalidations->sum(fn (StudentPeriodConvalidation $convalidation): int => (int) ($convalidation->attendance_lessons ?? 0));
        $absences = $convalidations->sum(fn (StudentPeriodConvalidation $convalidation): int => (int) ($convalidation->attendance_absences ?? 0));
        $justified = $convalidations->sum(fn (StudentPeriodConvalidation $convalidation): int => (int) ($convalidation->attendance_justified_absences ?? 0));

        return $this->attendanceCalculator->fromTotals(
            $lessons,
            max(0, $lessons - $absences),
            $justified,
        );
    }
}
