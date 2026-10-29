<?php

namespace App\Console\Commands;

use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrectAcademicYearCriteria extends Command
{
    protected $signature = 'academic-years:correct-criteria {enrollment : Matrícula que identifica o ano e a escola}
        {--passing-points= : Pontuação mínima anual confirmada}
        {--dependency-limit= : Limite confirmado de componentes em dependência}
        {--apply : Gravar os critérios e recalcular os resultados já registrados}';

    protected $description = 'Corrige critérios confirmados e resultados do ano sem reabrir nem alterar notas ou frequência.';

    public function handle(StudentFinalResultCalculator $calculator): int
    {
        $points = $this->option('passing-points');
        $limit = $this->option('dependency-limit');
        if (! is_numeric($points) || $points < 0 || $points > 100 || fmod((float) $points * 2, 1) !== 0.0
            || ! is_string($limit) || ! ctype_digit($limit) || (int) $limit > 30) {
            $this->error('Informe a pontuação em incrementos de 0,5 e o limite inteiro de dependências entre 0 e 30.');

            return self::FAILURE;
        }

        $enrollment = StudentEnrollment::query()->with('schoolClass.academicYear.school')->findOrFail($this->argument('enrollment'));
        $year = $enrollment->schoolClass->academicYear;
        $school = $year->school;
        $query = StudentEnrollment::query()
            ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->id))
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->whereNotNull('final_result_calculated_at');
        $this->info("Ano letivo #{$year->id}: {$points} pontos; {$limit} dependências. Resultados a conferir: {$query->count()}.");
        if (! $this->option('apply')) {
            $this->info('Conferência somente. Use --apply para registrar a correção confirmada.');

            return self::SUCCESS;
        }

        $counts = DB::transaction(function () use ($year, $school, $query, $calculator, $points, $limit): array {
            $year->update(['passing_points' => (float) $points]);
            $school->academicCriteria()->updateOrCreate(
                ['effective_from' => $year->starts_at->toDateString()],
                ['dependency_component_limit' => (int) $limit],
            );
            $school->unsetRelation('academicCriteria');
            $school->update(['dependency_component_limit' => $school->dependencyComponentLimitForDate()]);
            $counts = [];
            foreach ($query->orderBy('id')->get() as $item) {
                $result = $calculator->calculate($item);
                $item->update([
                    'final_result_status' => $result['status'],
                    'final_result_details' => $result['details'],
                    'final_result_calculated_at' => now(),
                    'final_result_calculated_by_person_id' => null,
                ]);
                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
            }

            return $counts;
        });

        $this->info('Critérios corrigidos. Resultados registrados: '.json_encode($counts, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
