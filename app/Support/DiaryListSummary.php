<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryExcludedDate;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DiaryListSummary
{
    public function __construct(private DiaryPeriodStatus $status) {}

    /** @param Collection<int, SchoolClassComponent> $assignments */
    public function forAssignments(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return collect();
        }

        $assignments = new EloquentCollection($assignments->all());
        $assignments->loadMissing([
            'component.startsPeriod', 'component.endsPeriod',
            'component.course.startsPeriod', 'component.course.endsPeriod',
            'component.course.academicYear.periods', 'component.course.academicYear.days',
            'schoolClass.startsPeriod', 'schoolClass.endsPeriod', 'schoolClass.academicYear',
            'schoolClass.schedules.slots', 'schoolClass.enrollments',
        ]);
        $attendance = $this->forDiaries(DiaryAttendanceRecord::query(), $assignments)->get()->groupBy(fn ($record) => $this->key($record));
        $contents = $this->forDiaries(DiaryContent::query(), $assignments)->get()->groupBy(fn ($record) => $this->key($record));
        $assessments = $this->forDiaries(DiaryAssessment::query(), $assignments)
            ->whereNotNull('school_assessment_rule_id')->where('is_recovery', false)
            ->with('results:id,diary_assessment_id,student_enrollment_id,score')
            ->get()->groupBy(fn ($record) => $this->key($record));
        $originGrades = StudentPeriodConvalidation::query()
            ->whereIn('student_enrollment_id', $assignments->pluck('schoolClass.enrollments')->flatten()->pluck('id')->unique())
            ->whereIn('curriculum_component_id', $assignments->pluck('curriculum_component_id')->unique())
            ->whereNotNull('score')->get();

        $excludedDates = DiaryExcludedDate::query()->whereIn('school_class_component_id', $assignments->pluck('id'))->get()->groupBy('school_class_component_id');

        return $assignments->mapWithKeys(function (SchoolClassComponent $assignment) use ($attendance, $contents, $assessments, $originGrades, $excludedDates): array {
            $key = $this->key($assignment);
            $component = $assignment->component;
            $course = $component->course;
            $year = $course->academicYear;
            $class = $assignment->schoolClass;
            $coursePeriods = $year->periods->filter(fn (AcademicPeriod $period): bool => $component->isActiveInPeriod($period)
                && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
                && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position)
            );
            $periods = $coursePeriods->filter(fn (AcademicPeriod $period): bool => (! $class->startsPeriod || $period->position >= $class->startsPeriod->position)
                && (! $class->endsPeriod || $period->position <= $class->endsPeriod->position)
            );
            $records = $attendance->get($key, collect())->filter(fn ($record) => $periods->contains('id', $record->academic_period_id) && $record->lesson_count > 0);
            $dayContents = $contents->get($key, collect())->filter(fn ($record) => $periods->contains('id', $record->academic_period_id) && filled(trim($record->content)));
            $schoolDays = $year->days->where('counts_as_school_day', true)->filter(fn ($day) => $periods->contains(
                fn (AcademicPeriod $period) => $day->date->betweenIncluded($period->starts_at, $period->ends_at)
            ));
            $forecastDays = $schoolDays->reject(fn ($day) => $excludedDates->get($assignment->id, collect())->contains(
                fn ($excluded) => $excluded->class_date->toDateString() === $day->date->toDateString()
                    && $periods->contains('id', $excluded->academic_period_id)
            ));
            $plannedByDate = collect();
            $assignmentSchedules = $class->schedules->filter(fn ($schedule) => $schedule->slots->where('type', 'aula')->contains('school_class_component_id', $assignment->id));
            $completeSchedule = $assignmentSchedules->isNotEmpty() && $schoolDays->isNotEmpty();
            foreach ($forecastDays as $day) {
                $date = $day->date->toDateString();
                $schedules = $assignmentSchedules->filter(fn ($schedule) => $schedule->starts_at->toDateString() <= $date
                    && (! $schedule->ends_at || $schedule->ends_at->toDateString() >= $date));
                $weeklySlots = $schedules->flatMap->slots->where('type', 'aula')->where('school_class_component_id', $assignment->id);
                // Um horário parcial, inclusive uma grade semanal incompleta, não representa o ano inteiro.
                if ($schedules->isEmpty() || ($component->weekly_lessons !== null && $weeklySlots->count() !== (int) $component->weekly_lessons)) {
                    $completeSchedule = false;
                }
                $lessons = $weeklySlots->where('weekday', $day->date->dayOfWeekIso)->count();
                if ($lessons > 0) {
                    $plannedByDate->put($date, $lessons);
                }
            }
            $planned = null;
            $source = null;
            if ($completeSchedule) {
                $planned = (int) $plannedByDate->sum();
                $source = 'schedule';
            } elseif ((int) $course->class_hour_minutes > 0 && $component->calculatedWorkloadHours($course) > 0) {
                $classShare = $coursePeriods->isNotEmpty() ? $periods->count() / $coursePeriods->count() : 1;
                $keptDaysShare = $schoolDays->isNotEmpty() ? $forecastDays->count() / $schoolDays->count() : 1;
                $planned = (int) round($component->calculatedWorkloadHours($course) * 60 / $course->class_hour_minutes * $classShare * $keptDaysShare);
                $source = $assignmentSchedules->isNotEmpty() ? 'matrix_partial_schedule' : 'matrix';
            } elseif ($assignmentSchedules->isNotEmpty()) {
                $source = 'partial_schedule';
            }
            $recordsByDate = $records->keyBy(fn ($record) => $record->class_date->toDateString());
            $timeline = $this->timeline($periods, $class, $year);
            $today = now()->toDateString();
            $dueFraction = $forecastDays->isNotEmpty()
                ? $forecastDays->filter(fn ($day) => $day->date->toDateString() <= $today)->count() / $forecastDays->count()
                : $timeline['elapsed_fraction'];
            $expectedToday = $source === 'schedule'
                ? (int) $plannedByDate->filter(fn ($lessons, $date) => $date <= $today)->sum()
                : ($planned === null ? null : (int) floor($planned * $dueFraction));
            if ($timeline['overdue'] && $planned !== null) {
                $expectedToday = $planned;
            }
            $attendanceToday = (int) $records->filter(fn ($record) => $record->class_date->toDateString() <= $today)->sum('lesson_count');
            $contentToday = (int) $dayContents->filter(fn ($record) => $record->class_date->toDateString() <= $today)
                ->sum(fn ($record) => $recordsByDate->get($record->class_date->toDateString())?->lesson_count ?? $plannedByDate->get($record->class_date->toDateString(), 0));
            $lastContent = $dayContents->filter(fn ($record) => $record->class_date->toDateString() <= $today)->max('class_date');
            $lastAttendance = $records->filter(fn ($record) => $record->class_date->toDateString() <= $today)->max('class_date');
            $contentPace = $this->pace($contentToday, $expectedToday, $timeline, $lastContent?->toDateString());
            $attendancePace = $this->pace($attendanceToday, $expectedToday, $timeline, $lastAttendance?->toDateString());
            $periodIssues = $periods->map(function (AcademicPeriod $period) use ($class, $records, $dayContents, $assessments, $key, $originGrades, $today): array {
                $attendanceDates = $records->where('academic_period_id', $period->id)->filter(fn ($record) => $record->class_date->toDateString() <= $today)->toBase()->map(fn ($record) => $record->class_date->toDateString())->unique();
                $contentDates = $dayContents->where('academic_period_id', $period->id)->filter(fn ($record) => $record->class_date->toDateString() <= $today)->toBase()->map(fn ($record) => $record->class_date->toDateString())->unique();
                $missingGrades = $period->ends_at->copy()->startOfDay()->lte(now()->startOfDay())
                    ? $this->status->missingGrades($class->enrollments->filter->isActive(), $assessments->get($key, collect())->where('academic_period_id', $period->id), $originGrades)
                    : 0;

                return [
                    'period' => $period,
                    'attendance_without_content' => $attendanceDates->diff($contentDates)->count(),
                    'content_without_attendance' => $contentDates->diff($attendanceDates)->count(),
                    'missing_grades' => $missingGrades,
                ];
            })->values();
            $issues = [
                'attendance_without_content' => $periodIssues->sum('attendance_without_content'),
                'content_without_attendance' => $periodIssues->sum('content_without_attendance'),
                'missing_grades' => $periodIssues->sum('missing_grades'),
            ];

            return [$assignment->id => [
                'planned' => $planned, 'source' => $source,
                'content' => $this->progress($contentToday, $planned),
                'attendance' => $this->progress($attendanceToday, $planned),
                'future' => [
                    'attendance_lessons' => (int) $records->filter(fn ($record) => $record->class_date->toDateString() > $today)->sum('lesson_count'),
                    'content_dates' => $dayContents->filter(fn ($record) => $record->class_date->toDateString() > $today)->pluck('class_date')->unique()->count(),
                ],
                'issues' => $issues, 'issue_count' => array_sum($issues),
                'periods' => $periodIssues,
                'timeliness' => [
                    'starts_at' => $timeline['starts_at'], 'ends_at' => $timeline['ends_at'],
                    'expected_today' => $expectedToday,
                    'expected_percentage' => $planned > 0 && $expectedToday !== null ? round($expectedToday * 100 / $planned, 1) : null,
                    'content' => $contentPace, 'attendance' => $attendancePace,
                    'urgency' => max($contentPace['urgency'], $attendancePace['urgency']),
                ],
            ]];
        });
    }

    private function progress(int $registered, ?int $planned): array
    {
        return ['registered' => $registered, 'percentage' => $planned > 0 ? round($registered * 100 / $planned, 1) : null];
    }

    private function timeline(Collection $periods, SchoolClass $class, AcademicYear $year): array
    {
        $start = ($periods->sortBy('starts_at')->first()?->starts_at ?? $class->starts_at)?->copy()->startOfDay();
        $end = $year->ends_at?->copy()->startOfDay();
        if ($class->starts_at && (! $start || $class->starts_at->gt($start))) {
            $start = $class->starts_at->copy()->startOfDay();
        }
        $today = now()->startOfDay();
        $valid = $start && $end && $end->gte($start);
        $elapsed = $valid ? max(0, min(1, ($start->diffInDays($today, false) + 1) / ($start->diffInDays($end) + 1))) : 0;

        return [
            'starts_at' => $start?->toDateString(), 'ends_at' => $end?->toDateString(),
            'elapsed_fraction' => $elapsed, 'overdue' => $valid && $today->gt($end),
        ];
    }

    private function pace(int $registeredToday, ?int $expectedToday, array $timeline, ?string $lastDate): array
    {
        $missing = $expectedToday === null ? null : max(0, $expectedToday - $registeredToday);
        $recent = ! $timeline['overdue'] && $lastDate !== null
            && $lastDate >= now()->startOfDay()->subDays(10)->toDateString();
        $delayed = $missing > 0 && ! $recent;
        $urgency = 0;
        if ($delayed) {
            $urgency = (int) round($missing / $expectedToday * $timeline['elapsed_fraction'] * 100);
            if ($timeline['elapsed_fraction'] >= .9) {
                $urgency = max(50, $urgency);
            }
            if ($timeline['overdue']) {
                $urgency = 100;
            }
        }

        return ['missing' => $missing, 'urgency' => $urgency, 'is_delayed' => $delayed, 'recent' => $recent, 'last_date' => $lastDate];
    }

    private function forDiaries(Builder $query, Collection $assignments): Builder
    {
        return $query->where(function (Builder $diaries) use ($assignments): void {
            foreach ($assignments as $assignment) {
                $diaries->orWhere(fn (Builder $diary) => $diary->where('school_class_id', $assignment->school_class_id)->where('curriculum_component_id', $assignment->curriculum_component_id));
            }
        });
    }

    private function key(Model $record): string
    {
        return $record->school_class_id.'-'.$record->curriculum_component_id;
    }
}
