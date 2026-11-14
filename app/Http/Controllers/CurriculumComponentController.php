<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\SchoolClassComponent;
use App\Support\CurriculumCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CurriculumComponentController extends Controller
{
    public function show(Request $request, AcademicYear $academicYear, AcademicCourse $course, CurriculumComponent $component): View
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('curriculum-components.show', [
            'academicYear' => $academicYear->load('school'),
            'course' => $course,
            'knowledgeAreas' => CurriculumCatalog::knowledgeAreasForCourse($course, $component->area),
            'curriculumSuggestions' => CurriculumCatalog::suggestionsForCourse($course, $component),
            'component' => $component->load('area', 'startsPeriod', 'endsPeriod'),
            'periods' => $academicYear->periods()->orderBy('position')->get(),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        [$data, $definition] = $this->validatedData($request, $academicYear, $course);
        $component = DB::transaction(function () use ($course, $data, $definition) {
            $component = $course->components()->make($data);
            $component->adoptCatalogDefinition($definition);

            return $component;
        });
        foreach ($course->classes as $class) {
            SchoolClassComponent::query()->firstOrCreate([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
            ], [
                'active' => true,
            ]);
        }
        $course->refreshWorkloadHours();

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', __('Componente curricular cadastrado com sucesso.'));
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicCourse $course, CurriculumComponent $component): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        [$data, $definition] = $this->validatedData($request, $academicYear, $course, $component);
        DB::transaction(function () use ($component, $data, $definition, $course): void {
            $component->fill($data)->adoptCatalogDefinition($definition);
            $course->refreshWorkloadHours();
        });

        return redirect()->route('academic-years.courses.components.show', [$academicYear, $course, $component])
            ->with('status', __('Componente curricular atualizado com sucesso.'));
    }

    public function destroy(Request $request, AcademicYear $academicYear, AcademicCourse $course, CurriculumComponent $component): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $component->delete();
        $course->refreshWorkloadHours();

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', __('Componente curricular removido com sucesso.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(
        Request $request,
        AcademicYear $academicYear,
        AcademicCourse $course,
        ?CurriculumComponent $component = null
    ): array {
        $definitions = CurriculumCatalog::definitionsForCourse($course, $component)->keyBy('id');

        $data = $request->validate([
            'catalog_component_id' => ['required', 'integer', Rule::in($definitions->modelKeys())],
            'starts_period_id' => ['nullable', Rule::in($this->allowedPeriodIds($academicYear, $course))],
            'ends_period_id' => ['nullable', Rule::in($this->allowedPeriodIds($academicYear, $course))],
            'workload_mode' => ['required', Rule::in(['weekly_lessons', 'workload_hours', 'periods'])],
            'weekly_lessons' => ['nullable', 'required_if:workload_mode,weekly_lessons', 'prohibited_unless:workload_mode,weekly_lessons', 'integer', 'min:1', 'max:99'],
            'workload_hours' => ['nullable', 'required_if:workload_mode,workload_hours', 'prohibited_unless:workload_mode,workload_hours', 'integer', 'min:1', 'max:99999'],
            'period_loads' => ['nullable', 'required_if:workload_mode,periods', 'prohibited_unless:workload_mode,periods', 'array', 'min:1'],
            'period_loads.*' => ['array:weekly_lessons,workload_hours'],
            'period_loads.*.weekly_lessons' => ['required', 'integer', 'min:0', 'max:99'],
            'period_loads.*.workload_hours' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'catalog_component_id.required' => __('Selecione um componente do catálogo.'),
            'catalog_component_id.in' => __('Selecione um componente do catálogo com etapa, formação e área compatíveis.'),
        ]);

        $this->ensureValidPeriodSpan($academicYear, $data['starts_period_id'] ?? null, $data['ends_period_id'] ?? null);

        $data['active'] = $request->boolean('active', true);
        if ($data['workload_mode'] === 'periods') {
            $allowed = $academicYear->periods->whereIn('id', $this->allowedPeriodIds($academicYear, $course))->keyBy('id');
            $start = $allowed->get($data['starts_period_id'] ?? null)?->position ?? PHP_INT_MIN;
            $end = $allowed->get($data['ends_period_id'] ?? null)?->position ?? PHP_INT_MAX;
            $loads = [];
            foreach ($data['period_loads'] as $id => $load) {
                $period = $allowed->get($id);
                if (! $period || $period->position < $start || $period->position > $end) {
                    throw ValidationException::withMessages(['period_loads' => __('Informe somente períodos pertencentes à duração do componente nesta matriz.')]);
                }
                if ((int) $load['weekly_lessons'] === 0 && isset($load['workload_hours'])) {
                    throw ValidationException::withMessages(['period_loads' => __('Uma carga de período requer pelo menos uma aula semanal.')]);
                }
                $loads[$id] = ['weekly_lessons' => (int) $load['weekly_lessons'], 'workload_hours' => isset($load['workload_hours']) ? (float) $load['workload_hours'] : null];
            }
            if (! collect($loads)->contains(fn ($load) => $load['weekly_lessons'] > 0)) {
                throw ValidationException::withMessages(['period_loads' => __('Informe aulas semanais em pelo menos um período.')]);
            }
            $data['period_loads'] = $loads;
            $data['weekly_lessons'] = $data['workload_hours'] = null;
        } elseif ($data['workload_mode'] === 'weekly_lessons') {
            $data['workload_hours'] = null;
            $data['period_loads'] = null;
        } else {
            $data['weekly_lessons'] = null;
            $data['period_loads'] = null;
        }
        unset($data['workload_mode']);
        $definition = $definitions->get((int) $data['catalog_component_id']);
        unset($data['catalog_component_id']);

        return [$data, $definition];
    }

    /**
     * @return list<int>
     */
    private function allowedPeriodIds(AcademicYear $academicYear, AcademicCourse $course): array
    {
        return $academicYear->periods()
            ->orderBy('position')
            ->pluck('id')
            ->all();
    }

    private function ensureValidPeriodSpan(AcademicYear $academicYear, ?int $startsPeriodId, ?int $endsPeriodId): void
    {
        if (! $startsPeriodId || ! $endsPeriodId) {
            return;
        }

        $periods = $academicYear->periods()->whereIn('id', [$startsPeriodId, $endsPeriodId])->get()->keyBy('id');
        $starts = $periods->get($startsPeriodId);
        $ends = $periods->get($endsPeriodId);

        if ($starts && $ends && $starts->position > $ends->position) {
            throw ValidationException::withMessages([
                'ends_period_id' => __('O período final do componente deve ser igual ou posterior ao período inicial.'),
            ]);
        }
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if ($academicYear->isReadOnly()) {
            throw ValidationException::withMessages([
                'closed_at' => __('Este ano letivo está fechado. Reabra o ano letivo antes de alterar componentes curriculares.'),
            ]);
        }

        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => __('Ano letivo aprovado só pode ter sua estrutura acadêmica alterada pela Administração global.'),
        ]);
    }
}
