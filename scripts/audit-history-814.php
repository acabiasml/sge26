<?php

use App\Models\AcademicCourse;
use App\Models\DiaryAssessmentResult;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Support\StudentReportCardBuilder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$builder = $app->make(StudentReportCardBuilder::class);
$enrollments = StudentEnrollment::query()
    ->where('person_id', 814)
    ->with(['courses', 'schoolClass.academicYear.school'])
    ->orderBy('id')
    ->get();

foreach ($enrollments as $enrollment) {
    if (! $enrollment->courses->contains('stage', AcademicCourse::STAGE_ELEMENTARY)) {
        continue;
    }

    $report = $builder->build($enrollment);
    $components = $report['annualComponents']
        ->filter(fn (array $item): bool => $item['component']->course?->stage === AcademicCourse::STAGE_ELEMENTARY);

    echo json_encode([
        'enrollment_id' => $enrollment->id,
        'school_id' => $enrollment->schoolClass->academicYear->school_id,
        'year' => $enrollment->schoolClass->academicYear->reference_year,
        'year_closed' => $enrollment->schoolClass->academicYear->isClosed(),
        'status' => $enrollment->status,
        'final_result_status' => $enrollment->final_result_status,
        'periods' => $report['periods']->count(),
        'components' => $components->count(),
        'components_with_complete_period' => $components->filter(fn (array $item): bool => $item['complete_periods'] > 0)->count(),
        'assessment_results' => DiaryAssessmentResult::query()->where('student_enrollment_id', $enrollment->id)->whereNotNull('score')->count(),
        'convalidations' => $enrollment->periodConvalidations()->count(),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
}

$history = StudentAcademicHistory::query()->findOrFail(48);
foreach ($history->years()->with('records')->get() as $year) {
    echo json_encode([
        'history_year_id' => $year->id,
        'source' => $year->source,
        'enrollment_id' => $year->student_enrollment_id,
        'grade_phase' => $year->grade_phase,
        'final_result' => $year->final_result,
        'records' => $year->records->count(),
        'records_with_score' => $year->records->whereNotNull('score_numeric')->count(),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
}
