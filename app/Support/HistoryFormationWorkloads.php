<?php

namespace App\Support;

use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryYear;

final class HistoryFormationWorkloads
{
    public static function formations(StudentAcademicHistory $history, ?StudentAcademicHistoryYear $selectedYear = null): array
    {
        $history->loadMissing('years', 'components.records');
        $years = $selectedYear ? collect([$selectedYear]) : $history->years;
        $base = $history->education_stage === 'tecnico' ? 'Formação Técnica Profissional' : 'Formação Geral Básica';
        $formations = collect([$base]);
        foreach ($years as $year) {
            if ($year->transcript_mode === 'no_transcription') {
                continue;
            }
            $formations = $formations->concat($history->components
                ->filter(fn ($component) => $component->records->where('student_academic_history_year_id', $year->id)->isNotEmpty())->pluck('formation')->filter());
            if ($year->transcript_mode === 'summary') {
                $formations = $formations->concat(array_keys($year->formation_workload_hours ?? []));
            }
        }

        return $formations->unique()->sortBy(fn ($formation) => array_search($formation, ['Formação Geral Básica', 'Parte Diversificada', 'Parte Complementar', 'Itinerário Formativo', 'Formação Técnica Profissional'], true) ?: 0)->values()->all();
    }

    public static function yearHours(StudentAcademicHistory $history, StudentAcademicHistoryYear $year): array
    {
        $formations = self::formations($history, $year);
        $result = array_fill_keys($formations, null);
        if ($year->transcript_mode === 'no_transcription') {
            return $result;
        }
        if ($year->transcript_mode === 'summary') {
            if ($year->formation_workload_hours !== null) {
                foreach ($year->formation_workload_hours as $formation => $hours) {
                    $result[$formation] = is_numeric($hours) ? (float) $hours : null;
                }
            }
            if (count($formations) === 1 && $result[$formations[0]] === null) {
                $result[$formations[0]] = $year->workload_hours !== null ? (float) $year->workload_hours : null;
            }

            return $result;
        }
        foreach ($formations as $formation) {
            $hours = $history->components->where('formation', $formation)->flatMap(fn ($component) => $component->records->where('student_academic_history_year_id', $year->id))->pluck('workload_hours')->filter(fn ($value) => $value !== null);
            $result[$formation] = $hours->isNotEmpty() ? round($hours->sum(), 2) : null;
        }
        if (count($formations) === 1 && $result[$formations[0]] === null && $year->workload_hours !== null) {
            $result[$formations[0]] = (float) $year->workload_hours;
        }

        return $result;
    }

    public static function totals(StudentAcademicHistory $history, ?StudentAcademicHistoryYear $selectedYear = null): array
    {
        $totals = array_fill_keys(self::formations($history, $selectedYear), ['planned' => null, 'completed' => null]);
        foreach ($selectedYear ? [$selectedYear] : $history->years as $year) {
            foreach (self::yearHours($history, $year) as $formation => $hours) {
                if ($hours !== null) {
                    $totals[$formation]['planned'] = round(($totals[$formation]['planned'] ?? 0) + $hours, 2);
                }
                if (! $year->displaysCompletedWorkload()) {
                    continue;
                }
                if ($year->transcript_mode === 'summary' && $year->source !== 'system') {
                    $completed = $hours;
                } else {
                    $values = $history->components->where('formation', $formation)->flatMap(fn ($component) => $component->records->where('student_academic_history_year_id', $year->id))->map(fn ($record) => $record->completedWorkloadHours($year))->filter(fn ($value) => $value !== null);
                    $completed = $values->isEmpty() ? ($year->source !== 'system' && count(self::formations($history, $year)) === 1 ? $hours : null) : $values->sum();
                }
                if ($completed !== null) {
                    $totals[$formation]['completed'] = round(($totals[$formation]['completed'] ?? 0) + $completed, 2);
                }
            }
        }

        return $totals;
    }

    public static function completedYearTotal(StudentAcademicHistoryYear $year, array $totals): ?float
    {
        if (! $year->displaysCompletedWorkload()) {
            return null;
        }

        $hours = array_column($totals, 'completed');
        if ($hours !== [] && ! in_array(null, $hours, true)) {
            return round(array_sum($hours), 2);
        }

        return $year->source !== 'system' ? $year->completedWorkloadHours() : null;
    }
}
