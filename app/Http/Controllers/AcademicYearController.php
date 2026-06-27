<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\School;
use App\Support\AcademicStructureStatus;
use App\Support\AcademicStructureValidator;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function create(Request $request, School $school): View
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        return view('academic-years.create', [
            'school' => $school,
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $data = $this->validatedData($request) + ['school_id' => $school->id];

        $year = AcademicYear::query()->create($data);
        $this->generateInitialCalendar($year);

        return redirect()->route('schools.academic-years.index', $school)
            ->with('status', 'Ano letivo cadastrado com sucesso.');
    }

    public function show(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-years.show', [
            'academicYear' => $academicYear->load([
                'school',
                'periods.assessmentRules',
                'days',
                'courses.components.area',
                'courses.classes',
                'classes.courses.components',
                'classes.enrollments',
                'classes.componentAssignments.teacher',
                'classes.componentAssignments.component',
                'classes.schedules.slots',
            ]),
            'structureIssues' => AcademicStructureValidator::forAcademicYear($academicYear),
            'yearStatus' => AcademicStructureStatus::academicYear($academicYear),
        ]);
    }

    public function edit(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-years.edit', [
            'academicYear' => $academicYear,
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $academicYear->update($this->validatedData($request, $academicYear));

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Ano letivo atualizado com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        if ($academicYear->periods()->exists()) {
            return redirect()->route('schools.academic-years.index', $academicYear->school_id)
                ->with('status', 'Não é possível excluir um ano letivo que possui períodos associados.');
        }

        $schoolId = $academicYear->school_id;
        $academicYear->delete();

        return redirect()->route('schools.academic-years.index', $schoolId)
            ->with('status', 'Ano letivo excluído com sucesso.');
    }

    public function approve(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $data = $request->validate([
            'approved_at' => ['required', 'date'],
        ]);

        $academicYear->update([
            'approved_at' => $data['approved_at'],
        ]);

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Calendário aprovado. Alterações posteriores devem ser tratadas com cautela.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?AcademicYear $academicYear = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reference_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'passing_points' => ['nullable', 'numeric', 'min:0', 'max:100', 'multiple_of:0.5'],
            'minimum_attendance_percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['minimum_school_days'] = $academicYear?->minimum_school_days ?? 200;
        $data['passing_points'] ??= $academicYear?->passing_points ?? 24;
        $data['minimum_attendance_percentage'] ??= $academicYear?->minimum_attendance_percentage ?? 75;

        return $data;
    }

    private function generateInitialCalendar(AcademicYear $academicYear): void
    {
        $now = now();
        $rows = [];

        foreach (CarbonPeriod::create($academicYear->starts_at, $academicYear->ends_at) as $date) {
            $isWeekend = $date->isWeekend();

            $rows[] = [
                'academic_year_id' => $academicYear->id,
                'date' => $date->toDateString(),
                'type' => $isWeekend ? CalendarDay::TYPE_WEEKEND : CalendarDay::TYPE_FINAL_VACATION,
                'counts_as_school_day' => false,
                'title' => $isWeekend ? null : 'Férias',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            CalendarDay::query()->insert($rows);
        }
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => 'Calendário aprovado só pode ser alterado pela Administração global.',
        ]);
    }
}
