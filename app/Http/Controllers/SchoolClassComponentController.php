<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassComponentSubstitution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolClassComponentController extends Controller
{
    public function update(
        Request $request,
        AcademicYear $academicYear,
        SchoolClass $class,
        SchoolClassComponent $classComponent
    ): RedirectResponse {
        $this->authorizeClassComponent($request, $academicYear, $class, $classComponent);

        $teacherIds = $this->teacherIds($academicYear);

        $data = $request->validate([
            'teacher_person_id' => ['nullable', Rule::in($teacherIds)],
            'active' => ['nullable', 'boolean'],
        ]);

        $classComponent->update([
            'teacher_person_id' => $data['teacher_person_id'] ?? null,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('academic-years.classes.show', [$academicYear, $class])
            ->with('status', 'Docência da turma atualizada com sucesso.');
    }

    public function storeSubstitution(
        Request $request,
        AcademicYear $academicYear,
        SchoolClass $class,
        SchoolClassComponent $classComponent
    ): RedirectResponse {
        $this->authorizeClassComponent($request, $academicYear, $class, $classComponent);

        $data = $request->validate([
            'substitute_teacher_person_id' => ['required', Rule::in($this->teacherIds($academicYear))],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $classComponent->substitutions()->create($data);

        return redirect()->route('academic-years.classes.show', [$academicYear, $class])
            ->with('status', 'Substituição docente cadastrada com sucesso.');
    }

    public function destroySubstitution(
        Request $request,
        AcademicYear $academicYear,
        SchoolClass $class,
        SchoolClassComponent $classComponent,
        SchoolClassComponentSubstitution $substitution
    ): RedirectResponse {
        $this->authorizeClassComponent($request, $academicYear, $class, $classComponent);
        abort_unless($substitution->school_class_component_id === $classComponent->id, 404);

        $substitution->delete();

        return redirect()->route('academic-years.classes.show', [$academicYear, $class])
            ->with('status', 'Substituição docente removida com sucesso.');
    }

    private function authorizeClassComponent(
        Request $request,
        AcademicYear $academicYear,
        SchoolClass $class,
        SchoolClassComponent $classComponent
    ): void {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($classComponent->school_class_id === $class->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        if ($academicYear->isClosed()) {
            throw ValidationException::withMessages([
                'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar docentes da turma.',
            ]);
        }
    }

    /**
     * @return array<int>
     */
    private function teacherIds(AcademicYear $academicYear): array
    {
        return Person::query()
            ->whereHas('schoolRoles', function ($query) use ($academicYear): void {
                $query->where('school_id', $academicYear->school_id)
                    ->where('role', PersonSchoolRole::ROLE_TEACHER)
                    ->where('active', true);
            })
            ->pluck('id')
            ->all();
    }

}
