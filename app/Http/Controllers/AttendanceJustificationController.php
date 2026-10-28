<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\DiaryAttendanceJustification;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceJustificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $schools = School::query()
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $request->user()->manageableSchoolIds()))
            ->orderBy('name')
            ->get();
        $school = $schools->firstWhere('id', $request->integer('school')) ?? $schools->first();
        $academicYears = $school
            ? AcademicYear::query()->where('school_id', $school->id)->orderByDesc('reference_year')->orderBy('name')->get()
            : collect();
        $academicYear = $academicYears->firstWhere('id', $request->integer('year'));
        $classes = $academicYear
            ? SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->when(! $request->user()->isAdministrator(), fn ($query) => $query->operational())
                ->orderBy('name')
                ->get()
            : collect();
        $schoolClass = $classes->firstWhere('id', $request->integer('class'));
        $enrollments = $school
            ? StudentEnrollment::query()
                ->with(['student', 'schoolClass.academicYear'])
                ->whereHas('schoolClass', fn ($query) => $query
                    ->whereHas('academicYear', fn ($years) => $years->where('school_id', $school->id))
                    ->when($academicYear, fn ($classes) => $classes->where('academic_year_id', $academicYear->id))
                    ->when($schoolClass, fn ($classes) => $classes->whereKey($schoolClass->id))
                    ->when(! $request->user()->isAdministrator(), fn ($classes) => $classes->operational()))
                ->orderByDesc('enrolled_at')
                ->get()
                ->filter(fn (StudentEnrollment $enrollment) => ! $enrollment->schoolClass->academicYear->isReadOnly())
                ->values()
            : collect();
        $justifications = $school
            ? DiaryAttendanceJustification::query()
                ->with(['enrollment.student', 'enrollment.schoolClass.academicYear', 'grantedBy', 'updatedBy'])
                ->whereHas('enrollment.schoolClass', fn ($query) => $query
                    ->whereHas('academicYear', fn ($years) => $years->where('school_id', $school->id))
                    ->when($academicYear, fn ($classes) => $classes->where('academic_year_id', $academicYear->id))
                    ->when($schoolClass, fn ($classes) => $classes->whereKey($schoolClass->id))
                    ->when(! $request->user()->isAdministrator(), fn ($classes) => $classes->operational()))
                ->orderByDesc('starts_at')
                ->get()
            : collect();

        return view('attendance-justifications.index', compact('schools', 'school', 'academicYears', 'academicYear', 'classes', 'schoolClass', 'enrollments', 'justifications'));
    }

    public function store(Request $request): RedirectResponse
    {
        $enrollment = StudentEnrollment::query()
            ->with('schoolClass.academicYear')
            ->findOrFail($request->integer('student_enrollment_id'));
        $academicYear = $enrollment->schoolClass?->academicYear;
        abort_unless($academicYear && $request->user()->canManageSchool($academicYear->school_id), 403);
        abort_unless($request->user()->isAdministrator() || $enrollment->schoolClass->isOperational(), 403);
        $this->ensureAcademicYearIsOpen($academicYear);

        $data = $request->validate([
            'student_enrollment_id' => ['required', Rule::in([$enrollment->id])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $this->validateEnrollmentPeriod($enrollment, $academicYear, $data);

        DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'],
            'granted_by_person_id' => $request->user()->person_id,
        ]);

        return redirect()->route('attendance-justifications.index', $this->indexFilters($enrollment, $academicYear))
            ->with('status', __('Justificativa de ausência registrada com sucesso.'));
    }

    public function edit(Request $request, DiaryAttendanceJustification $justification): View
    {
        $justification->load(['enrollment.student', 'enrollment.schoolClass.academicYear', 'grantedBy', 'updatedBy']);
        $academicYear = $this->authorizedAcademicYear($request, $justification);
        abort_if($academicYear->isReadOnly(), 403);

        $enrollments = StudentEnrollment::query()
            ->with(['student', 'schoolClass.academicYear'])
            ->whereHas('schoolClass', fn ($classes) => $classes
                ->whereHas('academicYear', fn ($years) => $years->where('school_id', $academicYear->school_id))
                ->when(! $request->user()->isAdministrator(), fn ($classes) => $classes->operational()))
            ->orderByDesc('enrolled_at')
            ->get()
            ->filter(fn (StudentEnrollment $enrollment) => ! $enrollment->schoolClass->academicYear->isReadOnly())
            ->values();

        return view('attendance-justifications.edit', compact('justification', 'enrollments', 'academicYear'));
    }

    public function update(Request $request, DiaryAttendanceJustification $justification): RedirectResponse
    {
        $justification->load('enrollment.schoolClass.academicYear');
        $originalYear = $this->authorizedAcademicYear($request, $justification);
        $this->ensureAcademicYearIsOpen($originalYear);

        $enrollment = StudentEnrollment::query()
            ->with('schoolClass.academicYear')
            ->findOrFail($request->integer('student_enrollment_id'));
        $academicYear = $enrollment->schoolClass?->academicYear;
        abort_unless($academicYear && $academicYear->school_id === $originalYear->school_id, 403);
        abort_unless($request->user()->isAdministrator() || $enrollment->schoolClass->isOperational(), 403);
        $this->ensureAcademicYearIsOpen($academicYear);

        $data = $request->validate([
            'student_enrollment_id' => ['required', Rule::in([$enrollment->id])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $this->validateEnrollmentPeriod($enrollment, $academicYear, $data);

        $changed = DB::transaction(function () use ($justification, $data, $request, $academicYear): bool {
            $current = DiaryAttendanceJustification::query()
                ->with('enrollment.schoolClass.academicYear')
                ->lockForUpdate()
                ->findOrFail($justification->id);
            $lockedYear = $this->authorizedAcademicYear($request, $current);
            abort_unless($lockedYear->school_id === $academicYear->school_id, 403);
            $this->ensureAcademicYearIsOpen($lockedYear);
            $current->fill($data);

            if (! $current->isDirty(['student_enrollment_id', 'starts_at', 'ends_at', 'reason'])) {
                return false;
            }

            $current->updated_by_person_id = $request->user()->person_id;
            $current->modification_count = ($current->modification_count ?? 0) + 1;
            $current->save();

            return true;
        });

        return redirect()->route('attendance-justifications.index', $this->indexFilters($enrollment, $academicYear))
            ->with('status', $changed ? __('Justificativa atualizada com sucesso.') : __('Nenhuma alteração foi feita.'));
    }

    public function destroy(Request $request, DiaryAttendanceJustification $justification): RedirectResponse
    {
        $justification->load('enrollment.schoolClass.academicYear');
        $academicYear = $this->authorizedAcademicYear($request, $justification);
        $this->ensureAcademicYearIsOpen($academicYear);

        $filters = $this->indexFilters($justification->enrollment, $academicYear);
        $justification->delete();

        return redirect()->route('attendance-justifications.index', $filters)
            ->with('status', __('Justificativa removida.'));
    }

    private function authorizedAcademicYear(Request $request, DiaryAttendanceJustification $justification): AcademicYear
    {
        $academicYear = $justification->enrollment?->schoolClass?->academicYear;
        abort_unless($academicYear && $request->user()->canManageSchool($academicYear->school_id), 403);
        abort_unless($request->user()->isAdministrator() || $justification->enrollment->schoolClass->isOperational(), 403);

        return $academicYear;
    }

    private function validateEnrollmentPeriod(StudentEnrollment $enrollment, AcademicYear $academicYear, array $data): void
    {
        $startsAt = $enrollment->enrolled_at?->toDateString() ?? $academicYear->starts_at->toDateString();
        $endsAt = $enrollment->transferred_at?->toDateString()
            ?? $enrollment->cancelled_at?->toDateString()
            ?? $academicYear->ends_at->toDateString();

        if ($data['starts_at'] < $startsAt || $data['ends_at'] > $endsAt) {
            throw ValidationException::withMessages([
                'starts_at' => __('A justificativa deve estar dentro da vigência da matrícula.'),
            ]);
        }
    }

    private function indexFilters(StudentEnrollment $enrollment, AcademicYear $academicYear): array
    {
        return [
            'school' => $academicYear->school_id,
            'year' => $academicYear->id,
            'class' => $enrollment->school_class_id,
        ];
    }

    private function ensureAcademicYearIsOpen(AcademicYear $academicYear): void
    {
        if (! $academicYear->isReadOnly()) {
            return;
        }

        throw ValidationException::withMessages([
            'closed_at' => __('Este ano letivo está fechado. Reabra o ano letivo antes de alterar justificativas de ausência.'),
        ]);
    }
}
