<?php

use App\Models\DiaryAssessment;
use App\Models\DiaryPeriodConfirmation;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use App\Support\DiaryPeriodStatus;

// Somente leitura. A saída contém dados pessoais e deve ser criptografada antes de sair do SSH.
$assignments = SchoolClassComponent::query()
    ->with(['schoolClass.academicYear.school', 'schoolClass.academicYear.periods', 'component'])
    ->whereHas('component', fn ($query) => $query->where('name', 'like', '%ngl%'))
    ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('reference_year', now()->year))
    ->get();
$rows = [];
foreach ($assignments as $assignment) {
    $class = $assignment->schoolClass;
    $year = $class->academicYear;
    $enrollments = $class->enrollments()->with('student:id,full_name')->where('status', StudentEnrollment::STATUS_ENROLLED)->get();
    foreach ($year->periods as $period) {
        if (! $assignment->component->isActiveInPeriod($period)) {
            continue;
        }
        $pending = app(DiaryPeriodStatus::class)->pending($assignment, $period);
        $confirmation = DiaryPeriodConfirmation::query()->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $assignment->curriculum_component_id)->where('academic_period_id', $period->id)->first();
        $assessments = DiaryAssessment::query()->with('results')->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $assignment->curriculum_component_id)->where('academic_period_id', $period->id)
            ->whereNotNull('school_assessment_rule_id')->where('is_recovery', false)->get();
        $convalidations = StudentPeriodConvalidation::query()->with('convalidatedBy:id,full_name')
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('academic_period_id', $period->id)->where('curriculum_component_id', $assignment->curriculum_component_id)
            ->get()->keyBy('student_enrollment_id');
        $missing = [];
        foreach ($enrollments as $enrollment) {
            $missingAssessments = $assessments->filter(fn ($assessment) => $assessment->results->firstWhere('student_enrollment_id', $enrollment->id)?->score === null);
            if ($missingAssessments->isEmpty()) {
                continue;
            }
            $origin = $convalidations->get($enrollment->id);
            $missing[] = [
                'enrollment_id' => $enrollment->id, 'student' => $enrollment->student?->full_name,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'assessments_missing' => $missingAssessments->map->only(['id', 'title', 'assessment_date'])->values(),
                'origin' => $origin ? [
                    'id' => $origin->id, 'score' => $origin->score, 'lessons' => $origin->attendance_lessons,
                    'absences' => $origin->attendance_absences, 'created_at' => $origin->created_at?->toIso8601String(),
                    'updated_at' => $origin->updated_at?->toIso8601String(), 'registered_by' => $origin->convalidatedBy?->full_name,
                ] : null,
            ];
        }
        $rows[] = [
            'school' => $year->school->name, 'district' => $year->school->district, 'class' => $class->name,
            'class_id' => $class->id, 'component_id' => $assignment->curriculum_component_id,
            'year_id' => $year->id, 'year_closed' => $year->isClosed(), 'period_id' => $period->id,
            'period' => $period->name, 'period_end' => $period->ends_at->toDateString(),
            'confirmed' => $confirmation?->confirmed ?? false, 'pending' => $pending, 'missing' => $missing,
        ];
    }
}
echo json_encode(['diaries' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
