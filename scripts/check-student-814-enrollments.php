<?php

use App\Models\AcademicCourse;
use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$enrollments = StudentEnrollment::query()
    ->where('person_id', 814)
    ->with(['schoolClass.academicYear', 'courses'])
    ->get();
$firstYear = $enrollments->first(fn (StudentEnrollment $item): bool =>
    (int) $item->schoolClass?->academicYear?->reference_year === 2026
    && $item->courses->contains('stage', AcademicCourse::STAGE_HIGH_SCHOOL));
$ninthYear = $enrollments->first(fn (StudentEnrollment $item): bool =>
    (int) $item->schoolClass?->academicYear?->reference_year === 2025
    && $item->courses->contains('stage', AcademicCourse::STAGE_ELEMENTARY));

if ($enrollments->count() !== 2
    || ! $firstYear || ! $ninthYear
    || (int) $firstYear->schoolClass->academicYear->school_id !== 3
    || (int) $ninthYear->schoolClass->academicYear->school_id !== 3) {
    fwrite(STDERR, 'As matrículas diferem das duas exibidas na auditoria visual.'.PHP_EOL);
    exit(1);
}

if (($argv[1] ?? null) === '--apply') {
    try {
        DB::transaction(function () use ($app, $firstYear, $ninthYear): void {
            $first = StudentEnrollment::query()->lockForUpdate()->findOrFail($firstYear->id);
            $ninth = StudentEnrollment::query()->with('schoolClass.academicYear')->findOrFail($ninthYear->id);
            if ($first->status !== StudentEnrollment::STATUS_TRANSFERRED
                || $first->transferred_at?->toDateString() !== '2026-07-20'
                || $ninth->final_result_status !== StudentEnrollment::FINAL_APPROVED
                || ! $ninth->exitDate()) {
                throw new RuntimeException('As condições para atualizar o resultado deixaram de valer.');
            }

            $result = $app->make(StudentFinalResultCalculator::class)->calculate($first);
            if ($result['status'] !== StudentEnrollment::FINAL_TRANSFERRED) {
                throw new RuntimeException('O cálculo não retornou transferência.');
            }
            if (! $first->final_result_calculated_at) {
                if (filled($first->final_result_status)) {
                    throw new RuntimeException('Já existe outro resultado registrado para o 1º ano.');
                }
                $first->update([
                    'final_result_status' => $result['status'],
                    'final_result_details' => $result['details'],
                    'final_result_calculated_at' => now(),
                    'final_result_calculated_by_person_id' => null,
                ]);
            }

            echo 'APPLIED first_year_transfer_result', PHP_EOL;
        });
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Resultado não atualizado: '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}

$firstYear->refresh();
$ninthYear->refresh();
echo json_encode([
    'first_year_enrollment_id' => $firstYear->id,
    'first_year_transfer_date' => $firstYear->transferred_at?->toDateString(),
    'first_year_result_is_transfer' => $firstYear->final_result_status === StudentEnrollment::FINAL_TRANSFERRED,
    'ninth_year_enrollment_id' => $ninthYear->id,
    'ninth_year_result_is_approved' => $ninthYear->final_result_status === StudentEnrollment::FINAL_APPROVED,
    'ninth_year_exit_date' => $ninthYear->exitDate()?->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
