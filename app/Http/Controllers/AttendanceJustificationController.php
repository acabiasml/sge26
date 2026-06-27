<?php

namespace App\Http\Controllers;

use App\Models\DiaryAttendanceJustification;
use App\Models\School;
use App\Models\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceJustificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $schools = School::query()
            ->where('active', true)
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
            ->orderBy('name')
            ->get();
        $school = $schools->firstWhere('id', $request->integer('school')) ?? $schools->first();
        $enrollments = $school
            ? StudentEnrollment::query()
                ->with(['student', 'schoolClass.academicYear'])
                ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('school_id', $school->id))
                ->orderByDesc('enrolled_at')
                ->get()
            : collect();
        $justifications = $school
            ? DiaryAttendanceJustification::query()
                ->with(['enrollment.student', 'enrollment.schoolClass.academicYear', 'grantedBy'])
                ->whereHas('enrollment.schoolClass.academicYear', fn ($query) => $query->where('school_id', $school->id))
                ->orderByDesc('starts_at')
                ->get()
            : collect();

        return view('attendance-justifications.index', compact('schools', 'school', 'enrollments', 'justifications'));
    }

    public function store(Request $request): RedirectResponse
    {
        $enrollment = StudentEnrollment::query()
            ->with('schoolClass.academicYear')
            ->findOrFail($request->integer('student_enrollment_id'));
        $academicYear = $enrollment->schoolClass?->academicYear;
        abort_unless($academicYear && $request->user()->canManageSchool($academicYear->school_id), 403);

        $data = $request->validate([
            'student_enrollment_id' => ['required', Rule::in([$enrollment->id])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $startsAt = $enrollment->enrolled_at?->toDateString() ?? $academicYear->starts_at->toDateString();
        $endsAt = $enrollment->transferred_at?->toDateString()
            ?? $enrollment->cancelled_at?->toDateString()
            ?? $academicYear->ends_at->toDateString();

        if ($data['starts_at'] < $startsAt || $data['ends_at'] > $endsAt) {
            throw ValidationException::withMessages([
                'starts_at' => 'A justificativa deve estar dentro da vigência da matrícula.',
            ]);
        }

        DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'],
            'granted_by_person_id' => $request->user()->person_id,
        ]);

        return redirect()->route('attendance-justifications.index', ['school' => $academicYear->school_id])
            ->with('status', 'Justificativa de ausência registrada com sucesso.');
    }

    public function destroy(Request $request, DiaryAttendanceJustification $justification): RedirectResponse
    {
        $justification->load('enrollment.schoolClass.academicYear');
        $academicYear = $justification->enrollment?->schoolClass?->academicYear;
        abort_unless($academicYear && $request->user()->canManageSchool($academicYear->school_id), 403);

        $schoolId = $academicYear->school_id;
        $justification->delete();

        return redirect()->route('attendance-justifications.index', ['school' => $schoolId])
            ->with('status', 'Justificativa removida.');
    }
}
