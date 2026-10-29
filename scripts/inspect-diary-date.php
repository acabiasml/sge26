<?php

// Executado pelo diagnóstico após o bootstrap, apenas para leitura e sem dados pessoais.
$assignments = \App\Models\SchoolClassComponent::query()
    ->with(['schoolClass.academicYear.school', 'component.course.academicYear', 'schoolClass.schedules.slots'])
    ->whereHas('schoolClass', fn ($query) => $query->where('name', 'like', '1%'))
    ->whereHas('component', fn ($query) => $query->where('name', 'like', '%ngl%'))
    ->get();
$rows = [];
foreach ($assignments as $assignment) {
    $class = $assignment->schoolClass;
    $year = $class->academicYear;
    $attendance = \App\Models\DiaryAttendanceRecord::query()
        ->where('school_class_id', $class->id)->where('curriculum_component_id', $assignment->curriculum_component_id)
        ->whereDate('class_date', $date)->get();
    $contents = \App\Models\DiaryContent::query()
        ->where('school_class_id', $class->id)->where('curriculum_component_id', $assignment->curriculum_component_id)
        ->whereDate('class_date', $date)->get();
    if ($attendance->isEmpty() && $contents->isEmpty()) {
        continue;
    }
    $calendar = $year->days()->whereDate('date', $date)->first();
    $periods = $year->periods()->whereIn('id', $attendance->pluck('academic_period_id')->merge($contents->pluck('academic_period_id')))->get();
    foreach ($periods as $period) {
        $method = new \ReflectionMethod(\App\Http\Controllers\TeacherDiaryController::class, 'scheduledDiaryDays');
        $days = $method->invoke(new \App\Http\Controllers\TeacherDiaryController, $year, $class, $assignment, $period);
        $rows[] = [
            'school' => $year->school->name, 'year_id' => $year->id, 'class' => $class->name,
            'component' => $assignment->component->name, 'period' => $period->name, 'date' => $date,
            'calendar_school_day' => $calendar?->counts_as_school_day,
            'visible_in_scheduled_editor' => $days->contains(fn ($day) => $day->date->toDateString() === $date),
            'attendance' => $attendance->map(fn ($record) => ['id' => $record->id, 'period_id' => $record->academic_period_id, 'lessons' => $record->lesson_count]),
            'contents' => $contents->map(fn ($record) => ['id' => $record->id, 'period_id' => $record->academic_period_id, 'filled' => filled($record->content)]),
            'pending' => app(\App\Support\DiaryPeriodStatus::class)->pending($assignment, $period),
        ];
    }
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
