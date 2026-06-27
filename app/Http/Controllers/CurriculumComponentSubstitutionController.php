<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\CurriculumComponentSubstitution;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CurriculumComponentSubstitutionController extends Controller
{
    public function store(Request $request, AcademicYear $academicYear, AcademicCourse $course, CurriculumComponent $component): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $teacherIds = Person::query()
            ->whereHas('schoolRoles', function ($query) use ($academicYear): void {
                $query->where('school_id', $academicYear->school_id)
                    ->where('role', PersonSchoolRole::ROLE_TEACHER)
                    ->where('active', true);
            })
            ->pluck('id')
            ->all();

        $data = $request->validate([
            'substitute_teacher_person_id' => ['required', Rule::in($teacherIds)],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $component->substitutions()->create($data);

        return redirect()->route('academic-years.courses.components.show', [$academicYear, $course, $component])
            ->with('status', 'Substituição docente cadastrada com sucesso.');
    }

    public function destroy(
        Request $request,
        AcademicYear $academicYear,
        AcademicCourse $course,
        CurriculumComponent $component,
        CurriculumComponentSubstitution $substitution
    ): RedirectResponse {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($component->academic_course_id === $course->id, 404);
        abort_unless($substitution->curriculum_component_id === $component->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $substitution->delete();

        return redirect()->route('academic-years.courses.components.show', [$academicYear, $course, $component])
            ->with('status', 'Substituição docente removida com sucesso.');
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
}
