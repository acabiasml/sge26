<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use Carbon\CarbonImmutable;

final class TechnicalCredentialEligibility
{
    public function __construct(private StudentReportCardBuilder $reports) {}

    public function evaluate(StudentEnrollment $enrollment, AcademicCourse $course): array
    {
        $course->loadMissing('technicalProgram', 'components.startsPeriod', 'components.endsPeriod', 'academicYear.periods', 'startsPeriod', 'endsPeriod');
        $program = $course->technicalProgram;
        if (! $program) {
            return ['modules' => [], 'final' => false, 'completed_at' => null, 'reasons' => ['Vincule a matriz ao cadastro do curso técnico.']];
        }
        $report = $this->reports->build($enrollment);
        $today = CarbonImmutable::now('America/Cuiaba')->toDateString();
        $modules = [];
        foreach ($program->modules as $module) {
            $issues = [];
            $ends = [];
            $moduleComponents = [];
            foreach ($module['components'] as $definition) {
                $component = $course->components->firstWhere('technical_program_component_key', $definition['key']);
                if (! $component || ! $component->active) {
                    $issues[] = $definition['name'].': componente não vinculado à matriz.';
                    $moduleComponents[] = $definition + ['final_average' => null];

                    continue;
                }
                if (abs($component->calculatedWorkloadHours($course) - (float) $definition['hours']) > .02) {
                    $issues[] = $component->name.': carga da matriz diverge do cadastro técnico.';
                }
                $periods = $course->academicYear->periods->filter(fn ($p) => $component->isActiveInPeriod($p)
                    && (! $course->startsPeriod || $p->position >= $course->startsPeriod->position)
                    && (! $course->endsPeriod || $p->position <= $course->endsPeriod->position));
                if ($periods->isEmpty()) {
                    $issues[] = $component->name.': período de realização não cadastrado.';
                }
                foreach ($periods as $period) {
                    $ends[] = $period->ends_at->toDateString();
                }
                $summary = $report['annualComponents']->first(fn ($s) => $s['component']->id === $component->id);
                $moduleComponents[] = $definition + ['final_average' => $summary && $summary['total_periods'] > 0 && $summary['complete_periods'] >= $summary['total_periods']
                    ? round((float) $summary['points'] / $summary['total_periods'], 2) : null];
                if (! $summary || $summary['complete_periods'] < $periods->count() || $summary['total_periods'] === 0) {
                    $issues[] = $component->name.': notas ainda incompletas.';
                } elseif (! ComponentApprovalCriteria::evaluate($summary, $report['passingPoints'])['passed']) {
                    $issues[] = $component->name.': média inferior a seis.';
                }
                if (! $summary || ($summary['attendance']['percentage'] ?? null) === null
                    || $summary['attendance']['percentage'] < $report['minimumAttendance']) {
                    $issues[] = $component->name.': frequência insuficiente ou não registrada.';
                }
            }
            $end = $ends ? max($ends) : null;
            if (! $end || $today < $end) {
                $issues[] = 'O módulo ainda não terminou'.($end ? ' (término em '.CarbonImmutable::parse($end)->format('d/m/Y').').' : '.');
            }
            if ($end && $enrollment->attendanceEndsAt() && $enrollment->attendanceEndsAt() < $end) {
                $issues[] = 'A matrícula foi encerrada antes do término do módulo.';
            }
            if ($enrollment->status === StudentEnrollment::STATUS_CANCELLED) {
                $issues[] = 'Matrícula cancelada.';
            }
            $modules[$module['key']] = array_merge($module, ['components' => $moduleComponents, 'eligible' => ! $issues, 'completed_at' => $end, 'issues' => array_values(array_unique($issues)), 'hours' => array_sum(array_column($module['components'], 'hours'))]);
        }
        $complete = $modules && collect($modules)->every(fn ($m) => $m['eligible']);
        $highSchool = StudentAcademicHistoryYear::whereHas('history', fn ($q) => $q->where('person_id', $enrollment->person_id)->where('education_stage', 'medio'))
            ->get()->contains(fn ($y) => ($y->grade_number === 3 || preg_match('/^3\s*[º°ª]?\s*(ano|s[eé]rie)/iu', $y->label))
                && in_array(mb_strtolower(trim($y->final_result ?? '')), ['aprovado', 'concluído', 'concluido'], true)
                && preg_match('/^\d{4}$/', $y->year) && (int) $y->year <= (int) substr($today, 0, 4));

        return ['modules' => $modules, 'final' => $complete && $highSchool, 'completed_at' => $complete ? max(array_column($modules, 'completed_at')) : null,
            'reasons' => array_values(array_filter([$complete ? null : 'Há módulos ainda não concluídos.', $highSchool ? null : 'Conclusão do Ensino Médio ainda não cadastrada.']))];
    }
}
