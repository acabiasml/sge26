<?php

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$calculator = $app->make(StudentFinalResultCalculator::class);
$years = AcademicYear::query()
    ->whereDoesntHave('days', fn ($query) => $query
        ->where('counts_as_school_day', true)
        ->whereBetween('date', ['2026-01-01', '2026-12-31']))
    ->orderBy('school_id')->orderBy('reference_year')->orderBy('id')->get();

foreach ($years as $year) {
    $enrollments = StudentEnrollment::query()
        ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->id))
        ->with('schoolClass.academicYear.school')
        ->get();
    $missing = $enrollments->filter(fn (StudentEnrollment $item): bool => blank($item->final_result_status)
        || $item->final_result_status === StudentEnrollment::FINAL_PENDING);
    $predictions = [];
    $pendingReasons = ['missing_attendance' => 0, 'incomplete_components' => 0];
    $calculationErrors = 0;
    foreach ($missing as $enrollment) {
        try {
            $result = $calculator->calculate($enrollment);
            $predictions[] = $result['status'];
            if ($result['status'] === StudentEnrollment::FINAL_PENDING) {
                $pendingReasons['missing_attendance'] += (int) ($result['details']['missing_attendance'] ?? false);
                $pendingReasons['incomplete_components'] += (int) (count($result['details']['pending_components'] ?? []) > 0);
            }
        } catch (Throwable) {
            $calculationErrors++;
        }
    }

    echo json_encode([
        'year_id' => $year->id,
        'school_id' => $year->school_id,
        'reference_year' => $year->reference_year,
        'closed' => $year->isClosed(),
        'enrollments' => $enrollments->count(),
        'calculated_count' => $enrollments->whereNotNull('final_result_calculated_at')->count(),
        'existing_results' => $enrollments->pluck('final_result_status')->map(fn ($value) => $value ?: 'null')->countBy()->all(),
        'to_calculate' => $missing->count(),
        'predicted_results' => collect($predictions)->countBy()->all(),
        'pending_reasons' => $pendingReasons,
        'calculation_errors' => $calculationErrors,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
}
