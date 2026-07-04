<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\SchoolClassComponent;
use App\Support\CurriculumCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'course' => $course->load('startsPeriod', 'endsPeriod'),
            'component' => $component->load('area', 'startsPeriod', 'endsPeriod'),
            'periods' => $academicYear->periods()->orderBy('position')->get(),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $component = $course->components()->create($this->validatedData($request, $academicYear, $course));
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
            ->with('status', 'Componente curricular cadastrado com sucesso.');
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicCourse $course, CurriculumComponent $component): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $component->update($this->validatedData($request, $academicYear, $course));
        $course->refreshWorkloadHours();

        return redirect()->route('academic-years.courses.components.show', [$academicYear, $course, $component])
            ->with('status', 'Componente curricular atualizado com sucesso.');
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
            ->with('status', 'Componente curricular removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, AcademicYear $academicYear, AcademicCourse $course): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'knowledge_area_id' => ['nullable', Rule::exists('knowledge_areas', 'id')],
            'starts_period_id' => ['nullable', Rule::in($this->allowedPeriodIds($academicYear, $course))],
            'ends_period_id' => ['nullable', Rule::in($this->allowedPeriodIds($academicYear, $course))],
            'weekly_lessons' => ['nullable', 'integer', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->ensureValidPeriodSpan($academicYear, $data['starts_period_id'] ?? null, $data['ends_period_id'] ?? null);

        $data['active'] = $request->boolean('active', true);
        $data['workload_hours'] = null;
        $data['knowledge_area_id'] = ($data['knowledge_area_id'] ?? null)
            ?: CurriculumCatalog::areaIdForComponent($course, $data['name']);

        return $data;
    }

    /**
     * @return list<int>
     */
    private function allowedPeriodIds(AcademicYear $academicYear, AcademicCourse $course): array
    {
        $periods = $academicYear->periods()->orderBy('position')->get();
        $startsPosition = $course->startsPeriod?->position ?? $periods->min('position');
        $endsPosition = $course->endsPeriod?->position ?? $periods->max('position');

        return $periods
            ->filter(fn ($period): bool => $period->position >= $startsPosition && $period->position <= $endsPosition)
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
                'ends_period_id' => 'O período final do componente deve ser igual ou posterior ao período inicial.',
            ]);
        }
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if ($academicYear->isClosed()) {
            throw ValidationException::withMessages([
                'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar componentes curriculares.',
            ]);
        }

        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => 'Ano letivo aprovado só pode ter sua estrutura acadêmica alterada pela Administração global.',
        ]);
    }
}
