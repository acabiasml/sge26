<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
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

        return $assignments->mapWithKeys(function (SchoolClassComponent $assignment) use ($attendance, $contents, $assessments, $originGrades): array {
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
            $plannedByDate = collect();
            $hasSchedule = $class->schedules->contains(fn ($schedule) => $schedule->slots->where('type', 'aula')->contains('school_class_component_id', $assignment->id));
            foreach ($year->days->where('counts_as_school_day', true) as $day) {
                if (! $periods->contains(fn (AcademicPeriod $period) => $day->date->betweenIncluded($period->starts_at, $period->ends_at))) {
                    continue;
                }
                $date = $day->date->toDateString();
                $lessons = $class->schedules->sum(fn ($schedule) => $schedule->starts_at->toDateString() <= $date && (! $schedule->ends_at || $schedule->ends_at->toDateString() >= $date)
                        ? $schedule->slots->where('type', 'aula')->where('weekday', $day->date->dayOfWeekIso)->where('school_class_component_id', $assignment->id)->count()
                        : 0
                );
                if ($lessons > 0) {
                    $plannedByDate->put($date, $lessons);
                }
            }
            $planned = null;
            $source = null;
            if ($hasSchedule && $year->days->where('counts_as_school_day', true)->isNotEmpty()) {
                $planned = (int) $plannedByDate->sum();
                $source = 'schedule';
            } elseif ((int) $course->class_hour_minutes > 0 && $component->calculatedWorkloadHours($course) > 0) {
                $classShare = $coursePeriods->isNotEmpty() ? $periods->count() / $coursePeriods->count() : 1;
                $planned = (int) round($component->calculatedWorkloadHours($course) * 60 / $course->class_hour_minutes * $classShare);
                $source = 'matrix';
            }
            $recordsByDate = $records->keyBy(fn ($record) => $record->class_date->toDateString());
            $contentLessons = $dayContents->sum(fn ($record) => $recordsByDate->get($record->class_date->toDateString())?->lesson_count ?? $plannedByDate->get($record->class_date->toDateString(), 0));
            $timeline = $this->timeline($periods, $class);
            $today = now()->toDateString();
            $schoolDays = $year->days->where('counts_as_school_day', true)->filter(fn ($day) => $periods->contains(fn (AcademicPeriod $period) => $day->date->betweenIncluded($period->starts_at, $period->ends_at))
            );
            $dueFraction = $schoolDays->isNotEmpty()
                ? $schoolDays->filter(fn ($day) => $day->date->toDateString() <= $today)->count() / $schoolDays->count()
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
            $contentPace = $this->pace($contentToday, $expectedToday, $timeline);
            $attendancePace = $this->pace($attendanceToday, $expectedToday, $timeline);
            $periodIssues = $periods->map(function (AcademicPeriod $period) use ($class, $records, $dayContents, $assessments, $key, $originGrades): array {
                $attendanceDates = $records->where('academic_period_id', $period->id)->toBase()->map(fn ($record) => $record->class_date->toDateString())->unique();
                $contentDates = $dayContents->where('academic_period_id', $period->id)->toBase()->map(fn ($record) => $record->class_date->toDateString())->unique();
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
                'content' => $this->progress((int) $contentLessons, $planned),
                'attendance' => $this->progress((int) $records->sum('lesson_count'), $planned),
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

    private function timeline(Collection $periods, SchoolClass $class): array
    {
        $start = ($periods->sortBy('starts_at')->first()?->starts_at ?? $class->starts_at)?->copy()->startOfDay();
        $end = ($periods->sortByDesc('ends_at')->first()?->ends_at ?? $class->ends_at)?->copy()->startOfDay();
        if ($class->starts_at && (! $start || $class->starts_at->gt($start))) {
            $start = $class->starts_at->copy()->startOfDay();
        }
        if ($class->ends_at && (! $end || $class->ends_at->lt($end))) {
            $end = $class->ends_at->copy()->startOfDay();
        }
        $today = now()->startOfDay();
        $valid = $start && $end && $end->gte($start);
        $elapsed = $valid ? max(0, min(1, ($start->diffInDays($today, false) + 1) / ($start->diffInDays($end) + 1))) : 0;

        return [
            'starts_at' => $start?->toDateString(), 'ends_at' => $end?->toDateString(),
            'elapsed_fraction' => $elapsed, 'overdue' => $valid && $today->gt($end),
        ];
    }

    private function pace(int $registeredToday, ?int $expectedToday, array $timeline): array
    {
        $missing = $expectedToday === null ? null : max(0, $expectedToday - $registeredToday);
        $urgency = 0;
        if ($missing > 0) {
            $urgency = (int) round($missing / $expectedToday * $timeline['elapsed_fraction'] * 100);
            if ($timeline['elapsed_fraction'] >= .9) {
                $urgency = max(50, $urgency);
            }
            if ($timeline['overdue']) {
                $urgency = 100;
            }
        }

        return ['missing' => $missing, 'urgency' => $urgency];
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
