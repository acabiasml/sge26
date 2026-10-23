<?php

use App\Models\AcademicCourse;
use App\Models\DiaryAssessmentResult;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Support\StudentReportCardBuilder;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$builder = $app->make(StudentReportCardBuilder::class);
if (($argv[1] ?? null) === '--sync') {
    try {
        DB::transaction(function () use ($app, $builder): void {
            $history = StudentAcademicHistory::query()->findOrFail(48);
            $enrollment = StudentEnrollment::query()->with('schoolClass.academicYear')->findOrFail(450);
            $report = $builder->build($enrollment);
            $components = $report['annualComponents']
                ->filter(fn (array $item): bool => $item['component']->course?->stage === AcademicCourse::STAGE_ELEMENTARY);
            $editorId = $history->updated_by_person_id ?: $history->created_by_person_id;

            if ((int) $history->person_id !== 814
                || (int) $history->school_id !== 3
                || $history->education_stage !== AcademicCourse::STAGE_ELEMENTARY
                || (int) $enrollment->person_id !== 814
                || (int) $enrollment->schoolClass->academicYear->school_id !== 3
                || (int) $enrollment->schoolClass->academicYear->reference_year !== 2025
                || ! $enrollment->schoolClass->academicYear->isClosed()
                || ! $editorId
                || $components->count() !== 9
                || $components->filter(fn (array $item): bool => $item['complete_periods'] > 0)->count() !== 9) {
                throw new RuntimeException('Os dados do histórico mudaram desde a auditoria.');
            }

            $updated = $app->make(UnifiedStudentHistorySynchronizer::class)->synchronize(
                $history->student,
                $history->school_id,
                $editorId,
                AcademicCourse::STAGE_ELEMENTARY,
            );
            $systemYear = $updated->years->firstWhere('student_enrollment_id', 450);
            if (! $systemYear || $systemYear->records->whereNotNull('score_numeric')->count() !== 9) {
                throw new RuntimeException('Os resultados do 9º ano não foram gerados integralmente.');
            }

            echo 'SYNCED history_id=48 enrollment_id=450 scored_components=9', PHP_EOL;
        });
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Sincronização não realizada: '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}

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
