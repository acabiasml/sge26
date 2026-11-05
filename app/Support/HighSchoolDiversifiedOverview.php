<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\CurriculumComponent;
use App\Models\StudentAcademicHistoryComponent;

final class HighSchoolDiversifiedOverview
{
    /** Consulta restrita à denominação Parte Diversificada no Ensino Médio. */
    public function build(): array
    {
        $components = StudentAcademicHistoryComponent::query()->select('id', 'student_academic_history_id', 'name', 'knowledge_area')->where('knowledge_area', 'Parte Diversificada')
            ->with(['history' => fn ($query) => $query->select('id', 'person_id', 'school_id', 'education_stage', 'stage', 'active', 'is_unified'),
                'history.student:id,full_name', 'history.school:id,name',
                'history.years:id,student_academic_history_id,stage',
                'records:id,student_academic_history_component_id,student_academic_history_year_id',
                'records.year:id,student_academic_history_id,label,year,stage,source,school_name'])->get()
            ->filter(function ($component) {
                $stage = $component->history ? CurriculumAreaStage::historyStage($component->history) : null;
                if ($stage === null) {
                    $stages = $component->records->map(fn ($record) => CurriculumAreaStage::stageFromLabel($record->year?->stage))->filter()->unique();
                    $stage = $stages->count() === 1 ? $stages->first() : null;
                }

                return $stage === AcademicCourse::STAGE_HIGH_SCHOOL;
            });
        $histories = $components->groupBy('student_academic_history_id')->map(function ($items) {
            $history = $items->first()->history;
            $years = $items->flatMap(fn ($component) => $component->records->pluck('year'))->filter()->unique('id');

            return [
                'person_id' => $history->person_id,
                'student' => $history->student?->full_name,
                'history_id' => $history->id,
                'active' => $history->active,
                'unified' => $history->is_unified,
                'school' => $history->school?->name,
                'components' => $items->pluck('name')->unique()->sort()->values()->all(),
                'years' => $years->map(fn ($year) => $year->only(['label', 'year', 'source', 'school_name']))->values()->all(),
            ];
        })->sortBy('student')->values()->all();
        $courses = CurriculumComponent::query()->whereHas('area', fn ($query) => $query->where('name', 'Parte Diversificada'))
            ->whereHas('course', fn ($query) => $query->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL))
            ->with('course.academicYear.school')->get()->groupBy('academic_course_id')->map(function ($items) {
                $course = $items->first()->course;

                return [
                    'course_id' => $course->id, 'course' => $course->name,
                    'school' => $course->academicYear?->school?->name,
                    'reference_year' => $course->academicYear?->reference_year,
                    'components' => $items->pluck('name')->unique()->sort()->values()->all(),
                ];
            })->values()->all();

        return ['history_components' => $components->count(), 'histories' => $histories, 'courses' => $courses];
    }
}
