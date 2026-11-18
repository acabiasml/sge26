<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\HistoryCurriculumComponent;
use App\Models\SchoolClassComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechnicalProgramMatrixController extends Controller
{
    public function store(Request $request, AcademicYear $academicYear, AcademicCourse $course)
    {
        abort_unless($course->academic_year_id === $academicYear->id && $course->technical_program_id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        if ($academicYear->isReadOnly() || ($academicYear->approved_at && ! $request->user()->isAdministrator())) {
            throw ValidationException::withMessages(['modules' => 'Reabra o ano letivo ou solicite à Administração a alteração desta matriz.']);
        }
        $program = $course->technicalProgram;
        $periods = $academicYear->periods->keyBy('id');
        $data = $request->validate(['modules' => ['required', 'array', 'min:1'], 'modules.*.key' => ['required', Rule::in(collect($program->modules)->pluck('key')->all())],
            'modules.*.start' => ['required', Rule::in($periods->modelKeys())], 'modules.*.end' => ['required', Rule::in($periods->modelKeys())]]);
        DB::transaction(function () use ($data, $program, $course, $periods): void {

            foreach ($data['modules'] as $selected) {
                if ($periods[$selected['start']]->position > $periods[$selected['end']]->position) {
                    throw ValidationException::withMessages(['modules' => 'O fim do módulo precisa ser igual ou posterior ao início.']);
                }
                $module = collect($program->modules)->firstWhere('key', $selected['key']);
                foreach ($module['components'] as $definition) {
                    $component = $course->components()->firstOrNew(['technical_program_component_key' => $definition['key']]);
                    $catalog = HistoryCurriculumComponent::find($definition['catalog_component_id'] ?? null);
                    if (! $catalog || $catalog->knowledge_area_id !== $program->knowledge_area_id) {
                        throw ValidationException::withMessages(['modules' => 'Vincule primeiro os componentes existentes no cadastro curricular à área deste curso técnico.']);
                    }
                    $component->fill(['workload_hours' => $definition['hours'], 'weekly_lessons' => null, 'period_loads' => null,
                        'starts_period_id' => $selected['start'], 'ends_period_id' => $selected['end'], 'active' => true]);
                    $component->adoptCatalogDefinition($catalog);
                    foreach ($course->classes as $class) {
                        SchoolClassComponent::firstOrCreate(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id], ['active' => true]);
                    }
                }
            }
            $course->refreshWorkloadHours();
        });

        return back()->with('status', 'Componentes do cadastro técnico vinculados à matriz.');
    }
}
