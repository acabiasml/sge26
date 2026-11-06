<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\StudentAcademicHistoryRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Propaga classificação; nunca calcula nem modifica resultados escolares. */
final class HistoryCurriculumPropagation
{
    public function matrixChanged(CurriculumComponent $matrix, HistoryCurriculumComponent $previous, HistoryCurriculumComponent $target): int
    {
        $references = HistoryCurriculumComponent::where('knowledge_area_id', $previous->knowledge_area_id)
            ->where('name', $previous->name)->where(function ($query) use ($previous, $matrix): void {
                $query->whereKey($previous->id);
                if ($matrix->course->stage === AcademicCourse::STAGE_TECHNICAL) {
                    $query->orWhere(fn ($projection) => $projection->where('education_stage', 'medio')->where('matrix_origin', true));
                }
            })->get();
        $count = 0;
        foreach ($references as $reference) {
            $successors = $this->auditedSuccessors($reference)->push($matrix)->unique('id');
            $destinations = $successors->flatMap->historyCatalogEntries->unique('id');
            $global = $reference->id === $target->id || (! $previous->matrixComponents()->exists() && ! $reference->matrixComponents()->exists()
                && $destinations->count() === 1 && $destinations->first()->id === $target->id);
            $count += $this->apply($reference, $target, $matrix, $global);
            if ($reference->id !== $target->id && ! $reference->matrixComponents()->exists() && ! $reference->historyComponents()->exists()) {
                HistoryCurriculumAlias::where('history_curriculum_component_id', $reference->id)->update(['history_curriculum_component_id' => $target->id]);
            }
        }

        return $count;
    }

    public function catalogChanged(HistoryCurriculumComponent $entry, ?string $previousStage = null): int
    {
        return $this->apply($entry, $entry->fresh('area'), null, false, $previousStage ?: $entry->education_stage);
    }

    /** Corrige mudanças passadas somente quando a auditoria identifica um destino único. */
    public function repairFromAudits(): int
    {
        $count = 0;
        $references = HistoryCurriculumComponent::where('matrix_origin', true)->whereDoesntHave('matrixComponents')
            ->whereHas('historyComponents')->get();
        foreach ($references as $reference) {
            $sources = $this->auditedSuccessors($reference);
            $targets = $sources->flatMap->historyCatalogEntries->unique('id');
            if ($targets->count() === 1) {
                $count += $this->apply($reference, $targets->first(), $sources->first(), true);
            }
        }

        return $count;
    }

    private function auditedSuccessors(HistoryCurriculumComponent $reference): Collection
    {
        $audits = AuditLog::where('auditable_type', CurriculumComponent::class)->where('action', 'updated')
            ->where(fn ($query) => $query->where('old_values->knowledge_area_id', $reference->knowledge_area_id)
                ->orWhere('old_values->name', $reference->name))->get();
        $sources = CurriculumComponent::whereKey($audits->pluck('auditable_id'))->with(['course', 'area', 'historyCatalogEntries'])->get()->keyBy('id');

        return $audits->filter(function ($audit) use ($sources, $reference): bool {
            $matrix = $sources->get($audit->auditable_id);
            if (! $matrix || ! $matrix->course) {
                return false;
            }
            $old = $audit->old_values ?? [];
            $oldArea = $old['knowledge_area_id'] ?? $matrix->knowledge_area_id;
            $oldName = $old['name'] ?? $matrix->name;
            $stageMatches = $matrix->course->stage === $reference->education_stage
                || ($matrix->course->stage === 'tecnico' && $reference->education_stage === 'medio');

            return $stageMatches && (int) $oldArea === $reference->knowledge_area_id
                && $this->key($oldName) === $this->key($reference->name);
        })->map(fn ($audit) => $sources->get($audit->auditable_id))->unique('id')->values();
    }

    private function apply(HistoryCurriculumComponent $previous, HistoryCurriculumComponent $target, ?CurriculumComponent $matrix, bool $global, ?string $manualStage = null): int
    {
        return DB::transaction(function () use ($previous, $target, $matrix, $global, $manualStage): int {
            $target->loadMissing('area');
            $matrix?->loadMissing(['course', 'area']);
            $components = $previous->historyComponents()->with(['history', 'catalogEntries', 'records.year.enrollment.courses'])->orderBy('student_academic_history_components.id')->lockForUpdate()->get();
            $changed = [];
            foreach ($components as $component) {
                $oldLabels = $component->only(['name', 'formation', 'knowledge_area']);
                $groups = [];
                foreach ($component->records as $record) {
                    $manual = $record->year?->source !== 'system';
                    $selected = $global || ($matrix
                        ? (! $manual && $this->belongsToMatrix($record, $matrix, $previous))
                        : ($manual && ($component->catalogEntries->count() === 1 || CurriculumAreaStage::stageFromLabel($record->year?->stage) === $manualStage)));
                    $labels = $selected ? [
                        'name' => $target->name,
                        'formation' => $matrix && ! $manual && $component->history->education_stage !== 'tecnico'
                            ? ($matrix->course->stage === 'tecnico' ? ($matrix->area?->formation ?: $component->formation) : CurriculumCatalog::formationLabelForComponent($matrix)) : $target->formation,
                        'knowledge_area' => $target->area->name,
                    ] : $oldLabels;
                    $key = json_encode([$selected, $labels], JSON_THROW_ON_ERROR);
                    $groups[$key] ??= ['selected' => $selected, 'labels' => $labels, 'records' => []];
                    $groups[$key]['records'][] = $record->id;
                }
                if ($component->records->isEmpty() && ($global || ! $matrix)) {
                    $groups[] = ['selected' => true, 'labels' => $target->only(['name', 'formation']) + ['knowledge_area' => $target->area->name], 'records' => []];
                }
                if (! collect($groups)->contains('selected', true)) {
                    continue;
                }
                // A parte não afetada mantém o componente original; os IDs e valores dos registros são preservados.
                $hasUnselected = collect($groups)->contains('selected', false);
                $reused = false;
                foreach ($groups as $group) {
                    if (! $group['selected']) {
                        continue;
                    }
                    $yearIds = $component->records->whereIn('id', $group['records'])->pluck('student_academic_history_year_id');
                    $equivalent = $component->history->components()->whereKeyNot($component->id)->where($group['labels'])
                        ->where('module_label', $component->module_label)->where('regulatory_reference', $component->regulatory_reference)
                        ->whereDoesntHave('records', fn ($query) => $query->whereIn('student_academic_history_year_id', $yearIds))->first();
                    $destination = $equivalent ?: (! $hasUnselected && ! $reused ? $component : $component->history->components()->create($component->only(['position', 'name', 'formation', 'knowledge_area', 'module_label', 'regulatory_reference'])));
                    $reused = true;
                    if ($destination->only(['name', 'formation', 'knowledge_area']) !== $group['labels']) {
                        $destination->adoptLabels($group['labels']);
                    }
                    if ($equivalent) {
                        $destination->catalogEntries()->syncWithoutDetaching([$target->id]);
                    } else {
                        $destination->catalogEntries()->sync([$target->id]);
                    }
                    if ($destination->id !== $component->id) {
                        DB::table('student_academic_history_records')->whereIn('id', $group['records'])->update(['student_academic_history_component_id' => $destination->id]);
                    }
                    $changed[] = ['component_id' => $destination->id, 'record_ids' => $group['records'], 'old' => $oldLabels, 'new' => $group['labels']];
                }
                if (! $hasUnselected && ! $component->records()->exists()) {
                    $component->delete();
                }
                if ($hasUnselected && ! $matrix) {
                    $remaining = $component->records()->with('year')->get();
                    if ($remaining->every(fn ($record) => $record->year?->source !== 'system'
                        && filled(CurriculumAreaStage::stageFromLabel($record->year?->stage))
                        && CurriculumAreaStage::stageFromLabel($record->year?->stage) !== $manualStage)) {
                        $component->catalogEntries()->detach($previous->id);
                    }
                }
            }
            if ($changed !== []) {
                AuditLog::create([
                    'actor_user_id' => auth()->id(), 'actor_person_id' => auth()->user()?->person_id,
                    'auditable_type' => HistoryCurriculumComponent::class, 'auditable_id' => $target->id,
                    'action' => 'updated', 'old_values' => ['catalog_component_id' => $previous->id],
                    'new_values' => ['classifications' => $changed],
                    'metadata' => ['operation' => 'history-curriculum:propagate', 'record_description' => 'Atualização da classificação dos históricos vinculados'],
                ]);
            }

            return count($changed);
        });
    }

    public function backfillMatrixOrigins(): int
    {
        $count = 0;
        $records = StudentAcademicHistoryRecord::whereNull('curriculum_component_id')
            ->whereHas('year', fn ($query) => $query->where('source', 'system'))
            ->with(['year.enrollment.courses', 'component.history', 'component.catalogEntries.matrixComponents.course.academicYear',
                'component.catalogEntries.matrixComponents.course.enrollments', 'component.catalogEntries.matrixComponents.endsPeriod', 'component.catalogEntries.matrixComponents.startsPeriod']);
        foreach ($records->lazyById(100) as $record) {
            $sources = $record->component->catalogEntries->flatMap->matrixComponents->unique('id');
            $candidates = $this->sourceCandidates($record, $sources);
            if ($candidates->count() === 1) {
                DB::table('student_academic_history_records')->where('id', $record->id)->update(['curriculum_component_id' => $candidates->first()->id]);
                $count++;
            }
        }

        return $count;
    }

    private function sourceCandidates(StudentAcademicHistoryRecord $record, Collection $sources): Collection
    {
        $courseIds = $record->year?->enrollment?->courses->modelKeys() ?? [];
        $direct = $sources->whereIn('academic_course_id', $courseIds)->values();
        if ($direct->isNotEmpty()) {
            return $direct;
        }
        if ($record->component->history->education_stage !== 'medio') {
            return collect();
        }
        $year = (int) $record->year?->year;
        $personId = $record->component->history->person_id;

        return $sources->filter(function ($source) use ($year, $personId): bool {
            $source->loadMissing(['course.academicYear', 'course.enrollments', 'startsPeriod', 'endsPeriod']);
            if ($source->course?->stage !== 'tecnico' || ! $source->course->enrollments->contains('person_id', $personId)) {
                return false;
            }
            $completionYear = (int) ($source->endsPeriod?->ends_at?->year ?? $source->startsPeriod?->ends_at?->year ?? $source->course->academicYear->ends_at?->year ?? $source->course->academicYear->reference_year);

            return $completionYear === $year;
        })->values();
    }

    private function belongsToMatrix(StudentAcademicHistoryRecord $record, CurriculumComponent $matrix, HistoryCurriculumComponent $previous): bool
    {
        if ($record->curriculum_component_id) {
            return $record->curriculum_component_id === $matrix->id;
        }
        $sources = $previous->matrixComponents()->get()->push($matrix)->unique('id');
        $candidates = $this->sourceCandidates($record, $sources);

        return $candidates->count() === 1 && $candidates->first()->id === $matrix->id;
    }

    private function key(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
