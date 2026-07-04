<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;

class AcademicYearClosureStatus
{
    /**
     * @return array<int, array{level: string, message: string, detail?: string}>
     */
    public function issues(AcademicYear $academicYear): array
    {
        $academicYear->loadMissing([
            'periods.diaryConsolidation',
            'classes.enrollments',
            'days',
        ]);

        $issues = [];

        if (! $academicYear->approved_at) {
            $issues[] = [
                'level' => 'error',
                'message' => 'O calendário ainda não foi aprovado.',
                'detail' => 'Registre a data de aprovação antes de fechar o ano letivo.',
            ];
        }

        if ($academicYear->periods->isEmpty()) {
            $issues[] = [
                'level' => 'error',
                'message' => 'Nenhum período avaliativo foi cadastrado.',
                'detail' => 'O fechamento depende da consolidação dos períodos avaliativos.',
            ];
        }

        foreach ($academicYear->periods->sortBy('position') as $period) {
            if (! $period->diaryConsolidation?->consolidated) {
                $issues[] = [
                    'level' => 'error',
                    'message' => "O período {$period->name} ainda não foi consolidado.",
                    'detail' => 'Consolide os diários do período antes do fechamento do ano letivo.',
                ];
            }
        }

        $enrollments = $this->enrollments($academicYear);
        $pendingFinalResults = $enrollments
            ->filter(fn (StudentEnrollment $enrollment): bool => blank($enrollment->final_result_status)
                || $enrollment->final_result_status === StudentEnrollment::FINAL_PENDING);

        if ($pendingFinalResults->isNotEmpty()) {
            $issues[] = [
                'level' => 'error',
                'message' => $pendingFinalResults->count().' matrícula(s) sem resultado final calculado.',
                'detail' => 'Calcule os resultados finais nas turmas antes de fechar o ano letivo.',
            ];
        }

        if ($academicYear->schoolDayCount() < (int) $academicYear->minimum_school_days) {
            $issues[] = [
                'level' => 'warning',
                'message' => 'O calendário está abaixo do mínimo de dias letivos configurado.',
                'detail' => 'Confira se este ano letivo realmente pode ser encerrado com essa contagem.',
            ];
        }

        if ($enrollments->isEmpty()) {
            $issues[] = [
                'level' => 'warning',
                'message' => 'Nenhuma matrícula foi encontrada neste ano letivo.',
                'detail' => 'Feche apenas se este calendário realmente não teve estudantes vinculados.',
            ];
        }

        return $issues;
    }

    public function canClose(AcademicYear $academicYear): bool
    {
        return collect($this->issues($academicYear))
            ->doesntContain(fn (array $issue): bool => $issue['level'] === 'error');
    }

    /**
     * @return array{
     *     periods: Collection<int, array{name: string, starts_at: string|null, ends_at: string|null, consolidated: bool, consolidated_at: string|null, consolidated_by: string|null}>,
     *     classes: Collection<int, array{class: SchoolClass, enrollments_count: int, pending_results: int, results: Collection<string, int>}>,
     *     totals: array{periods: int, consolidated_periods: int, classes: int, enrollments: int, pending_results: int}
     * }
     */
    public function overview(AcademicYear $academicYear): array
    {
        $academicYear->loadMissing([
            'periods.diaryConsolidation.consolidatedBy',
            'classes.courses',
            'classes.enrollments.student',
            'classes.enrollments.courses',
        ]);

        $periods = $academicYear->periods
            ->sortBy('position')
            ->values()
            ->map(fn (AcademicPeriod $period): array => [
                'name' => $period->name,
                'starts_at' => $period->starts_at?->format('d/m/Y'),
                'ends_at' => $period->ends_at?->format('d/m/Y'),
                'consolidated' => (bool) $period->diaryConsolidation?->consolidated,
                'consolidated_at' => $period->diaryConsolidation?->consolidated_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'consolidated_by' => $period->diaryConsolidation?->consolidatedBy?->full_name,
            ]);

        $classes = $academicYear->classes
            ->sortBy('name')
            ->values()
            ->map(function (SchoolClass $class): array {
                $enrollments = $class->enrollments;
                $pendingResults = $enrollments
                    ->filter(fn (StudentEnrollment $enrollment): bool => blank($enrollment->final_result_status)
                        || $enrollment->final_result_status === StudentEnrollment::FINAL_PENDING)
                    ->count();

                return [
                    'class' => $class,
                    'enrollments_count' => $enrollments->count(),
                    'pending_results' => $pendingResults,
                    'results' => $enrollments
                        ->groupBy(fn (StudentEnrollment $enrollment): string => $enrollment->finalResultLabel())
                        ->map->count()
                        ->sortKeys(),
                ];
            });

        return [
            'periods' => $periods,
            'classes' => $classes,
            'totals' => [
                'periods' => $periods->count(),
                'consolidated_periods' => $periods->where('consolidated', true)->count(),
                'classes' => $classes->count(),
                'enrollments' => $classes->sum('enrollments_count'),
                'pending_results' => $classes->sum('pending_results'),
            ],
        ];
    }

    /**
     * @return Collection<int, StudentEnrollment>
     */
    private function enrollments(AcademicYear $academicYear): Collection
    {
        return $academicYear->classes
            ->flatMap(fn ($class) => $class->enrollments)
            ->values();
    }
}
