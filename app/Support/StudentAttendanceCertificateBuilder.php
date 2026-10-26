<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\CurriculumComponent;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class StudentAttendanceCertificateBuilder
{
    public function __construct(
        private readonly AttendanceSummaryCalculator $attendanceCalculator,
        private readonly StudentEnrollmentContinuity $continuity,
    ) {}

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
        $result = $this->buildEnrollment($enrollment, $startsAt, $endsAt, $period);
        $enrollment->loadMissing('periodConvalidations.period');
        $covered = $enrollment->periodConvalidations
            ->filter(fn (StudentPeriodConvalidation $item): bool => $period
                ? $item->academic_period_id === $period->id
                : $item->period->starts_at->toDateString() >= $startsAt->toDateString()
                    && $item->period->ends_at->toDateString() <= $endsAt->toDateString())
            ->map(fn (StudentPeriodConvalidation $item): string => $item->period->position.'-'.$item->curriculum_component_id)
            ->all();

        foreach ($this->continuity->previous($enrollment) as $source) {
            $source->loadMissing('courses.components.course', 'schoolClass.academicYear.periods');
            $excluded = $source->schoolClass->academicYear->periods
                ->flatMap(fn (AcademicPeriod $sourcePeriod) => $source->courses
                    ->flatMap(fn (AcademicCourse $course) => $course->components
                        ->filter(fn ($component): bool => in_array(
                            $sourcePeriod->position.'-'.$this->matchingCurrentComponentId($enrollment, $component),
                            $covered,
                            true,
                        ))
                        ->map(fn ($component): string => $sourcePeriod->id.'-'.$component->id)))
                ->all();
            $prior = $this->buildEnrollment($source, $startsAt, $endsAt, null, $excluded);
            foreach ($result['matrices'] as $index => $matrix) {
                $matching = $prior['matrices']->first(fn (array $item): bool => $this->continuity->courseKey($item['course']) === $this->continuity->courseKey($matrix['course']));
                if ($matching) {
                    $matrix['attendance'] = $this->attendanceCalculator->aggregate(collect([
                        $matrix['attendance'], $matching['attendance'],
                    ]));
                    $result['matrices']->put($index, $matrix);
                }
            }
        }
        $result['attendance'] = $this->attendanceCalculator->aggregate($result['matrices']->pluck('attendance'));

        return $result;
    }

    private function matchingCurrentComponentId(StudentEnrollment $enrollment, CurriculumComponent $sourceComponent): ?int
    {
        $enrollment->loadMissing('courses.components.course');

        return $enrollment->courses->flatMap->components
            ->first(fn ($component): bool => $this->continuity->componentKey($component) === $this->continuity->componentKey($sourceComponent))?->id;
    }

    /** @param array<int, string> $excluded */
    private function buildEnrollment(
        StudentEnrollment $enrollment,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?AcademicPeriod $period = null,
        array $excluded = [],
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
        $recordEndsAt = min($endsAt->toDateString(), $enrollment->attendanceEndsAt() ?? $endsAt->toDateString());

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
        if ($excluded !== []) {
            $records = $records->reject(fn (DiaryAttendanceRecord $record): bool => in_array(
                $record->academic_period_id.'-'.$record->curriculum_component_id,
                $excluded,
                true,
            ))->values();
        }

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
        if ($excluded !== []) {
            $convalidations = $convalidations->reject(fn (StudentPeriodConvalidation $item): bool => in_array(
                $item->academic_period_id.'-'.$item->curriculum_component_id,
                $excluded,
                true,
            ))->values();
        }

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
