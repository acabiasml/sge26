<?php

namespace App\Console\Commands;

use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use App\Support\StudentReportCardBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrectImportedPeriodGrade extends Command
{
    protected $signature = 'diaries:correct-imported-grade {enrollment} {component} {period} {score}
        {--expected= : Nota atual esperada para evitar alterações sobre dados divergentes}
        {--apply : Registrar a correção solicitada e recalcular o resultado desta matrícula}';

    protected $description = 'Corrige a nota de período importada de uma avaliação única, com auditoria e recálculo individual.';

    public function handle(StudentReportCardBuilder $builder, StudentFinalResultCalculator $calculator): int
    {
        $score = $this->argument('score');
        $expected = $this->option('expected');
        if (! is_numeric($score) || $score < 0 || $score > 10 || ! is_numeric($expected)) {
            $this->error('Informe uma nota de 0 a 10 e a nota atual esperada.');

            return self::FAILURE;
        }

        $enrollment = StudentEnrollment::query()->findOrFail($this->argument('enrollment'));
        $report = $builder->build($enrollment);
        $periods = $report['periodReports']->filter(fn ($item) => $item['period']->academic_year_id === $report['academicYear']->id
            && $item['period']->position === (int) $this->argument('period'));
        $items = $periods->flatMap(fn ($item) => $item['components'])
            ->filter(fn ($item) => mb_strtolower(trim($item['component']->name)) === mb_strtolower(trim($this->argument('component'))))->values();
        if ($items->count() !== 1) {
            $this->error('O período e o componente devem identificar um único lançamento desta matrícula.');

            return self::FAILURE;
        }
        $item = $items->first();
        $regular = $item['assessments']->where('is_recovery', false);
        $recoveryHasScore = $item['assessments']->where('is_recovery', true)->contains(fn ($assessment) => $assessment->results->contains(fn ($result) => $result->score !== null));
        $assessment = $regular->first();
        if ($regular->count() !== 1 || $recoveryHasScore || ! $assessment->legacy_source || (float) $assessment->maximum_score !== 10.0) {
            $this->error('A correção exige uma avaliação importada única na escala de 0 a 10, sem recuperação lançada.');

            return self::FAILURE;
        }

        $result = $assessment->results->firstWhere('student_enrollment_id', $enrollment->id);
        if (! $result || $result->score === null || (float) $result->score !== (float) $expected || (float) $item['average']['value'] !== (float) $expected) {
            $this->error('A nota atual diverge da nota esperada. Nenhuma alteração foi aplicada.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Lançamento importado identificado. A correção pode ser registrada com --apply.');

            return self::SUCCESS;
        }

        $componentId = $item['component']->id;
        $periodId = $item['period']->id;
        DB::transaction(function () use ($result, $enrollment, $score, $expected, $builder, $calculator, $componentId, $periodId): void {
            $result = $result->newQuery()->lockForUpdate()->findOrFail($result->id);
            if ((float) $result->score !== (float) $expected) {
                throw new \RuntimeException('O lançamento mudou durante a conferência. Correção cancelada.');
            }
            $result->update([
                'score' => (float) $score,
                'updated_by_person_id' => null,
                'notes' => trim(($result->notes ? $result->notes."\n" : '').'Correção administrativa solicitada da nota importada.'),
            ]);
            $final = $calculator->calculate($enrollment);
            $enrollment->update([
                'final_result_status' => $final['status'], 'final_result_details' => $final['details'],
                'final_result_calculated_at' => now(), 'final_result_calculated_by_person_id' => null,
            ]);
            $updatedReport = $builder->build($enrollment);
            $updatedPeriod = $updatedReport['periodReports']->firstWhere('period.id', $periodId);
            $updated = $updatedPeriod['components']->firstWhere('component.id', $componentId);
            if (! $updated || (float) $updated['average']['value'] !== (float) $score) {
                throw new \RuntimeException('A média do período não corresponde à correção solicitada. Alteração revertida.');
            }
        });
        $this->info('Correção registrada e resultado individual recalculado. Resultado: '.$enrollment->finalResultLabel().'.');

        return self::SUCCESS;
    }
}
