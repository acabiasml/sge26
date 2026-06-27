<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Support\AcademicStructureStatus;
use App\Support\AcademicStructureValidator;
use App\Support\CurriculumCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicCourseController extends Controller
{
    public function create(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.create', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'course' => new AcademicCourse([
                'status' => 'planejado',
                'class_hour_minutes' => 50,
                'active' => true,
            ]),
        ]);
    }

    public function show(Request $request, AcademicYear $academicYear, AcademicCourse $course): View
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.show', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'course' => $course->load([
                'components.area',
                'components.startsPeriod',
                'components.endsPeriod',
                'startsPeriod',
                'endsPeriod',
                'classes.enrollments.student',
            ]),
            'courseStatus' => AcademicStructureStatus::course($course),
            'structureIssues' => AcademicStructureValidator::forCourse($course),
            'knowledgeAreas' => \App\Models\KnowledgeArea::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'curriculumSuggestions' => CurriculumCatalog::suggestionsForCourse($course),
        ]);
    }

    public function edit(Request $request, AcademicYear $academicYear, AcademicCourse $course): View
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.edit', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'course' => $course,
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $this->validatedData($request, $academicYear);
        $course = $academicYear->courses()->create($data);

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', 'Curso cadastrado com sucesso.');
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $course->update($this->validatedData($request, $academicYear, $course));

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', 'Matriz atualizada com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $course->delete();

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Curso removido com sucesso.');
    }

    public function duplicate(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $course->load('components');

        $duplicatedCourse = DB::transaction(function () use ($academicYear, $course): AcademicCourse {
            $duplicatedCourse = $academicYear->courses()->create([
                'starts_period_id' => $course->starts_period_id,
                'ends_period_id' => $course->ends_period_id,
                'name' => $this->nextDuplicateName($academicYear, $course->name),
                'stage' => $course->stage,
                'modality' => $course->modality,
                'status' => 'planejado',
                'workload_hours' => $course->workload_hours,
                'class_hour_minutes' => $course->class_hour_minutes,
                'notes' => $course->notes,
                'active' => $course->active,
            ]);

            foreach ($course->components as $component) {
                $duplicatedCourse->components()->create([
                    'knowledge_area_id' => $component->knowledge_area_id,
                    'starts_period_id' => $component->starts_period_id,
                    'ends_period_id' => $component->ends_period_id,
                    'name' => $component->name,
                    'weekly_lessons' => $component->weekly_lessons,
                    'workload_hours' => $component->workload_hours,
                    'notes' => $component->notes,
                    'active' => $component->active,
                ]);
            }

            $duplicatedCourse->refreshWorkloadHours();

            return $duplicatedCourse;
        });

        return redirect()->route('academic-years.courses.edit', [$academicYear, $duplicatedCourse])
            ->with('status', 'Matriz duplicada com sucesso. Ajuste o nome e os períodos conforme necessário.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, AcademicYear $academicYear, ?AcademicCourse $course = null): array
    {
        $periodIds = $academicYear->periods()->pluck('id')->all();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_courses')
                    ->where('academic_year_id', $academicYear->id)
                    ->ignore($course?->id),
            ],
            'stage' => ['required', Rule::in(array_keys(AcademicCourse::STAGE_LABELS))],
            'modality' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'starts_period_id' => ['nullable', Rule::in($periodIds)],
            'ends_period_id' => ['nullable', Rule::in($periodIds)],
            'class_hour_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);
        $data['workload_hours'] = $course?->calculatedWorkloadHours() ?? 0;

        return $data;
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => 'Ano letivo aprovado só pode ter sua estrutura acadêmica alterada pela Administração global.',
        ]);
    }

    private function nextDuplicateName(AcademicYear $academicYear, string $name): string
    {
        $baseName = 'Cópia de '.$name;

        if (! $academicYear->courses()->where('name', $baseName)->exists()) {
            return $baseName;
        }

        $counter = 2;

        do {
            $candidate = $baseName.' '.$counter;
            $counter++;
        } while ($academicYear->courses()->where('name', $candidate)->exists());

        return $candidate;
    }
}
