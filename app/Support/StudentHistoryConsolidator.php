<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Facades\DB;

class StudentHistoryConsolidator
{
    public function stage(StudentAcademicHistory $history): ?string
    {
        if ($history->education_stage) {
            return $history->education_stage;
        }
        $stages = $history->years()->pluck('stage')->filter()->unique();
        if ($stages->count() > 1) {
            return null;
        }

        return array_search($stages->first() ?: $history->stage, AcademicCourse::STAGE_LABELS, true) ?: null;
    }

    /** Move os lançamentos, preservando seus IDs, notas e cargas horárias. */
    public function consolidate(StudentAcademicHistory $target): void
    {
        DB::transaction(function () use ($target): void {
            $sources = StudentAcademicHistory::query()
                ->where('person_id', $target->person_id)->where('is_unified', false)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($sources as $source) {
                $years = $source->years()->with('records.component')->lockForUpdate()->get();
                $moved = false;
                foreach ($years as $year) {
                    $stage = array_search($year->stage, AcademicCourse::STAGE_LABELS, true)
                        ?: (blank($year->stage) ? $this->stage($source) : null);
                    if ($stage === AcademicCourse::STAGE_TECHNICAL && ($source->technical_course_id !== $target->technical_course_id || $year->source === 'system')) {
                        continue;
                    }
                    if ($stage !== $target->education_stage) {
                        continue;
                    }
                    foreach ($year->records as $record) {
                        $original = $record->component;
                        $component = $target->components()->firstOrCreate([
                            'name' => $original->name,
                            'formation' => $original->formation,
                            'knowledge_area' => $original->knowledge_area,
                            'module_label' => $original->module_label,
                            'regulatory_reference' => $original->regulatory_reference,
                        ], ['position' => $target->components()->count() + 1]);
                        $record->update(['student_academic_history_component_id' => $component->id]);
                    }
                    $year->update([
                        'student_academic_history_id' => $target->id,
                        'position' => $target->years()->count() + 1,
                        'source' => 'external',
                        'student_enrollment_id' => null,
                        'grade_number' => $year->grade_number ?: app(StudentAcademicHistoryCompleteness::class)
                            ->gradeNumber((string) $year->grade_phase, $stage),
                    ]);
                    $moved = true;
                }
                if ($moved) {
                    foreach (['notes', 'legal_basis'] as $field) {
                        if (filled($source->$field) && ! str_contains((string) $target->$field, $source->$field)) {
                            $target->$field = collect([$target->$field, $source->$field])->filter()->join("\n");
                        }
                    }
                    $target->save();
                    if (! $source->years()->exists()) {
                        $source->update(['active' => false]);
                    }
                }
            }
        });
    }
}
