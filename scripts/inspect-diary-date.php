<?php

use App\Http\Controllers\TeacherDiaryController;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolClassComponent;
use App\Support\DiaryListSummary;
use App\Support\DiaryPeriodStatus;

// Executado pelo diagnóstico após o bootstrap, apenas para leitura e sem dados pessoais.
$assignments = SchoolClassComponent::query()
    ->with(['schoolClass.academicYear.school', 'component.course.academicYear', 'schoolClass.schedules.slots'])
    ->whereHas('schoolClass', fn ($query) => $query->where('name', 'like', '1%'))
    ->whereHas('component', fn ($query) => $query->where('name', 'like', '%ngl%'))
    ->get();
$rows = [];
foreach ($assignments as $assignment) {
    $class = $assignment->schoolClass;
    $year = $class->academicYear;
    $attendance = DiaryAttendanceRecord::query()
        ->where('school_class_id', $class->id)->where('curriculum_component_id', $assignment->curriculum_component_id)
        ->whereDate('class_date', $date)->get();
    $contents = DiaryContent::query()
        ->where('school_class_id', $class->id)->where('curriculum_component_id', $assignment->curriculum_component_id)
        ->whereDate('class_date', $date)->get();
    if ($attendance->isEmpty() && $contents->isEmpty()) {
        continue;
    }
    $calendar = $year->days()->whereDate('date', $date)->first();
    $overview = app(DiaryListSummary::class)->forAssignments(collect([$assignment]))->get($assignment->id);
    $periods = $year->periods()->whereIn('id', $attendance->pluck('academic_period_id')->merge($contents->pluck('academic_period_id')))->get();
    foreach ($periods as $period) {
        $method = new ReflectionMethod(TeacherDiaryController::class, 'scheduledDiaryDays');
        $days = $method->invoke(new TeacherDiaryController, $year, $class, $assignment, $period);
        $rows[] = [
            'school' => $year->school->name, 'year_id' => $year->id, 'class' => $class->name,
            'component' => $assignment->component->name, 'period' => $period->name, 'date' => $date,
            'calendar_school_day' => $calendar?->counts_as_school_day,
            'visible_in_scheduled_editor' => $days->contains(fn ($day) => $day->date->toDateString() === $date),
            'attendance' => $attendance->map(fn ($record) => ['id' => $record->id, 'period_id' => $record->academic_period_id, 'lessons' => $record->lesson_count]),
            'contents' => $contents->map(fn ($record) => ['id' => $record->id, 'period_id' => $record->academic_period_id, 'filled' => filled($record->content)]),
            'pending' => app(DiaryPeriodStatus::class)->pending($assignment, $period),
            'list_progress' => [
                'planned' => $overview['planned'], 'source' => $overview['source'],
                'content' => $overview['content'], 'attendance' => $overview['attendance'],
                'issues' => $overview['issues'], 'issue_count' => $overview['issue_count'],
            ],
        ];
    }
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
