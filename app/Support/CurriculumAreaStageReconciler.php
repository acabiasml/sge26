<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;

final class CurriculumAreaStageReconciler
{
    /** O relatório contém somente contagens e nomes de áreas, sem dados de estudantes. */
    public function inspect(bool $apply = false): array
    {
        $report = ['curriculum_components' => 0, 'history_components' => 0, 'unclassified_history_components' => 0, 'unclassified_groups' => [], 'groups' => []];
        foreach (CurriculumComponent::with(['course', 'area'])->when($apply, fn ($query) => $query->lockForUpdate())->lazyById(200) as $component) {
            $stage = $component->course?->stage;
            $name = $component->area?->name;
            if (CurriculumAreaStage::compatible($name, $stage)) {
                continue;
            }
            $target = CurriculumAreaStage::canonicalName($name, $stage);
            $this->addGroup($report, 'curriculum_components', $stage, $name, $target);
            if ($apply && CurriculumAreaStage::compatible($target, $stage)) {
                $component->update(['knowledge_area_id' => CurriculumAreaStage::areaForStage($component->area, $stage)->id]);
            }
        }
        foreach (StudentAcademicHistoryComponent::with(['history.years', 'records.year'])->whereNotNull('knowledge_area')
            ->when($apply, fn ($query) => $query->lockForUpdate())->lazyById(200) as $component) {
            $stage = $component->history ? CurriculumAreaStage::historyStage($component->history) : null;
            if ($stage === null) {
                $recordStages = $component->records->map(fn ($record) => CurriculumAreaStage::stageFromLabel($record->year?->stage))->filter()->unique();
                $stage = $recordStages->count() === 1 ? $recordStages->first() : null;
            }
            if ($stage === null) {
                $report['unclassified_history_components']++;
                $group = ['area' => $component->knowledge_area, 'has_years' => (bool) $component->history?->years->isNotEmpty(), 'has_records' => $component->records->isNotEmpty()];
                $key = json_encode($group);
                $report['unclassified_groups'][$key] ??= $group + ['count' => 0];
                $report['unclassified_groups'][$key]['count']++;

                continue;
            }
            $name = $component->knowledge_area;
            if (CurriculumAreaStage::compatible($name, $stage)) {
                continue;
            }
            $target = CurriculumAreaStage::canonicalName($name, $stage);
            $this->addGroup($report, 'history_components', $stage, $name, $target);
            if ($apply && CurriculumAreaStage::compatible($target, $stage)) {
                $component->update(['knowledge_area' => $target]);
                AuditLog::create([
                    'school_id' => $component->history->school_id,
                    'auditable_type' => StudentAcademicHistory::class,
                    'auditable_id' => $component->student_academic_history_id,
                    'action' => 'updated',
                    'old_values' => ['knowledge_area' => $name],
                    'new_values' => ['knowledge_area' => $target],
                    'metadata' => ['operation' => 'curriculum:reconcile-stage-areas', 'component_id' => $component->id,
                        'record_description' => 'Correção da área curricular por etapa'],
                ]);
            }
        }
        $report['groups'] = array_values($report['groups']);
        $report['unclassified_groups'] = array_values($report['unclassified_groups']);

        return $report;
    }

    private function addGroup(array &$report, string $type, string $stage, string $name, string $target): void
    {
        $report[$type]++;
        $key = json_encode([$type, $stage, $name, $target]);
        $report['groups'][$key] ??= ['type' => $type, 'stage' => $stage, 'from' => $name,
            'to' => CurriculumAreaStage::compatible($target, $stage) ? $target : null, 'count' => 0];
        $report['groups'][$key]['count']++;
    }
}
