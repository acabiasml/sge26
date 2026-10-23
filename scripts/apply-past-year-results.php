<?php

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expectedEnrollmentCounts = [
    1 => 30,
    2 => 29,
    3 => 26,
    4 => 18,
    8 => 83,
    9 => 99,
    11 => 85,
    14 => 14,
];
$calculator = $app->make(StudentFinalResultCalculator::class);

try {
    $candidateIds = AcademicYear::query()
        ->whereDoesntHave('days', fn ($query) => $query
            ->where('counts_as_school_day', true)
            ->whereBetween('date', ['2026-01-01', '2026-12-31']))
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    if ($candidateIds !== array_keys($expectedEnrollmentCounts)) {
        throw new RuntimeException('O conjunto de anos letivos mudou desde a auditoria.');
    }

    foreach ($expectedEnrollmentCounts as $yearId => $expectedCount) {
        $counts = DB::transaction(function () use ($yearId, $expectedCount, $calculator): array {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($yearId);
            if (! $year->isClosed() || $year->days()
                ->where('counts_as_school_day', true)
                ->whereBetween('date', ['2026-01-01', '2026-12-31'])
                ->exists()) {
                throw new RuntimeException('Um ano letivo deixou de atender ao critério auditado.');
            }

            $enrollments = StudentEnrollment::query()
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $yearId))
                ->with('schoolClass.academicYear.school')
                ->orderBy('id')
                ->get();
            if ($enrollments->count() !== $expectedCount) {
                throw new RuntimeException('A quantidade de matrículas mudou desde a auditoria.');
            }

            $statuses = [];
            foreach ($enrollments as $enrollment) {
                if (filled($enrollment->final_result_status) && $enrollment->final_result_calculated_at) {
                    $statuses[] = $enrollment->final_result_status;
                    continue;
                }
                if (filled($enrollment->final_result_status)) {
                    throw new RuntimeException('Uma matrícula recebeu resultado sem data de cálculo.');
                }

                $result = $calculator->calculate($enrollment);
                $enrollment->update([
                    'final_result_status' => $result['status'],
                    'final_result_details' => $result['details'],
                    'final_result_calculated_at' => now(),
                    'final_result_calculated_by_person_id' => null,
                ]);
                $statuses[] = $result['status'];
            }

            return collect($statuses)->countBy()->all();
        });

        echo json_encode([
            'year_id' => $yearId,
            'enrollments' => $expectedCount,
            'registered_results' => $counts,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
        flush();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Registro interrompido: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
