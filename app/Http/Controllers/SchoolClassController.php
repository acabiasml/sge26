<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Support\AcademicStructureStatus;
use App\Support\AcademicStructureValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function create(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('school-classes.create', [
            'academicYear' => $academicYear->load('school', 'courses.components', 'periods'),
            'class' => new SchoolClass([
                'starts_at' => $academicYear->starts_at,
                'ends_at' => $academicYear->ends_at,
                'active' => true,
            ]),
            'readyCourses' => $this->readyCourses($academicYear),
        ]);
    }

    public function show(Request $request, AcademicYear $academicYear, SchoolClass $class): View
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('school-classes.show', [
            'academicYear' => $academicYear->load(['school', 'classes.courses']),
            'class' => $class->load([
                'courses.components',
                'startsPeriod',
                'endsPeriod',
                'enrollments',
                'componentAssignments.component.area',
                'componentAssignments.component.course',
                'componentAssignments.teacher',
                'componentAssignments.substitutions.substituteTeacher',
                'schedules.slots.componentAssignment.component',
            ]),
            'classStatus' => AcademicStructureStatus::schoolClass($class),
            'structureIssues' => AcademicStructureValidator::forClass($class),
            'teachers' => $this->teachers($academicYear),
        ]);
    }

    public function edit(Request $request, AcademicYear $academicYear, SchoolClass $class): View
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('school-classes.edit', [
            'academicYear' => $academicYear->load('school', 'courses.components', 'periods'),
            'class' => $class->load('courses', 'startsPeriod', 'endsPeriod'),
            'readyCourses' => $this->readyCourses($academicYear),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $this->validatedData($request, $academicYear);
        $courseIds = $data['course_ids'];
        unset($data['course_ids']);

        $coursesWithoutComponents = $academicYear->courses()
            ->whereIn('id', $courseIds)
            ->whereDoesntHave('components', fn ($query) => $query->where('active', true))
            ->pluck('name')
            ->all();

        if ($coursesWithoutComponents !== []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Cadastre os componentes da matriz antes de criar turma para: '.implode(', ', $coursesWithoutComponents).'.',
            ]);
        }

        $class = $academicYear->classes()->create($data);
        $class->courses()->sync($courseIds);
        $this->syncComponentAssignments($class);

        return redirect()->route('academic-years.classes.show', [$academicYear, $class])
            ->with('status', 'Turma cadastrada com sucesso.');
    }

    public function update(Request $request, AcademicYear $academicYear, SchoolClass $class): RedirectResponse
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $this->validatedData($request, $academicYear, $class);
        $courseIds = $data['course_ids'];
        unset($data['course_ids']);

        $coursesWithoutComponents = $academicYear->courses()
            ->whereIn('id', $courseIds)
            ->whereDoesntHave('components', fn ($query) => $query->where('active', true))
            ->pluck('name')
            ->all();

        if ($coursesWithoutComponents !== []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Cadastre os componentes da matriz antes de vincular turma para: '.implode(', ', $coursesWithoutComponents).'.',
            ]);
        }

        $class->update($data);
        $class->courses()->sync($courseIds);
        $this->syncComponentAssignments($class);

        return redirect()->route('academic-years.classes.show', [$academicYear, $class])
            ->with('status', 'Turma atualizada com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, SchoolClass $class): RedirectResponse
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $class->delete();

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Turma removida com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, AcademicYear $academicYear, ?SchoolClass $class = null): array
    {
        $courseIds = $academicYear->courses()->pluck('id')->all();
        $periodIds = $academicYear->periods()->pluck('id')->all();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_classes')
                    ->where('academic_year_id', $academicYear->id)
                    ->ignore($class?->id),
            ],
            'shift' => ['nullable', 'string', 'max:255'],
            'starts_period_id' => ['nullable', Rule::in($periodIds)],
            'ends_period_id' => ['nullable', Rule::in($periodIds)],
            'starts_at' => ['nullable', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', Rule::in($courseIds)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);
        $data['starts_at'] ??= $academicYear->starts_at->toDateString();
        $data['ends_at'] ??= $academicYear->ends_at->toDateString();
        $this->ensureValidPeriodSpan($academicYear, $data['starts_period_id'] ?? null, $data['ends_period_id'] ?? null);

        return $data;
    }

    private function readyCourses(AcademicYear $academicYear)
    {
        return $academicYear->courses()
            ->with('components')
            ->orderBy('name')
            ->get()
            ->filter->hasMatrixComponents();
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
                'ends_period_id' => 'O período final da turma deve ser igual ou posterior ao período inicial.',
            ]);
        }
    }

    private function syncComponentAssignments(SchoolClass $class): void
    {
        $componentIds = $class->courses()
            ->with('components')
            ->get()
            ->flatMap(fn ($course) => $course->components->where('active', true)->pluck('id'))
            ->unique()
            ->values();

        foreach ($componentIds as $componentId) {
            SchoolClassComponent::query()->firstOrCreate([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $componentId,
            ], [
                'active' => true,
            ]);
        }

        $class->componentAssignments()
            ->whereNotIn('curriculum_component_id', $componentIds)
            ->delete();
    }

    private function teachers(AcademicYear $academicYear)
    {
        return Person::query()
            ->whereHas('schoolRoles', function ($query) use ($academicYear): void {
                $query->where('school_id', $academicYear->school_id)
                    ->where('role', PersonSchoolRole::ROLE_TEACHER)
                    ->where('active', true);
            })
            ->orderBy('full_name')
            ->get();
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if ($academicYear->isClosed()) {
            throw ValidationException::withMessages([
                'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar turmas.',
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
