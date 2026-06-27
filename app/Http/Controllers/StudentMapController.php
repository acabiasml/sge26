<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StudentMapController extends Controller
{
    public function show(Request $request, Person $person): View
    {
        abort_unless($this->canSeeStudentMap($request, $person), 403);

        $person->load([
            'contacts',
            'studentEnrollments.schoolClass.academicYear.school',
            'studentEnrollments.courses',
        ]);

        $enrollments = $person->studentEnrollments
            ->sortByDesc(fn (StudentEnrollment $enrollment) => $enrollment->enrolled_at?->toDateString())
            ->values();
        $enrollmentIds = $enrollments->pluck('id')->all();

        $assessmentResults = DiaryAssessmentResult::query()
            ->with([
                'assessment.period',
                'assessment.component.area',
                'assessment.component.course',
                'enrollment.schoolClass.academicYear.school',
            ])
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereNotNull('score')
            ->get()
            ->sortBy([
                ['assessment.assessment_date', 'desc'],
                ['assessment.title', 'asc'],
            ])
            ->values();

        $attendanceEntries = DiaryAttendanceEntry::query()
            ->with([
                'record.period',
                'record.component.area',
                'record.component.course',
                'enrollment.schoolClass.academicYear.school',
                'enrollment.attendanceJustifications',
            ])
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->get();

        $documents = IssuedDocument::query()
            ->with('school')
            ->where('person_id', $person->id)
            ->latest('issued_at')
            ->limit(12)
            ->get();

        $auditLogs = AuditLog::query()
            ->with('actorPerson')
            ->where(function ($query) use ($person, $enrollmentIds): void {
                $query->where(function ($personLogs) use ($person): void {
                    $personLogs
                        ->where('auditable_type', Person::class)
                        ->where('auditable_id', $person->id);
                });

                if ($enrollmentIds !== []) {
                    $query->orWhere(function ($enrollmentLogs) use ($enrollmentIds): void {
                        $enrollmentLogs
                            ->where('auditable_type', StudentEnrollment::class)
                            ->whereIn('auditable_id', $enrollmentIds);
                    });
                }
            })
            ->latest('created_at')
            ->limit(12)
            ->get();

        return view('student-map.show', [
            'person' => $person,
            'enrollments' => $enrollments,
            'assessmentResults' => $assessmentResults,
            'attendanceSummary' => $this->attendanceSummary($attendanceEntries),
            'documents' => $documents,
            'auditLogs' => $auditLogs,
            'canManagePerson' => $request->user()->canManagePeople(),
        ]);
    }

    private function canSeeStudentMap(Request $request, Person $person): bool
    {
        $user = $request->user();

        if ($user->person_id === $person->id) {
            return true;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return $person->schoolRoles()
            ->whereIn('school_id', $user->manageableSchoolIds())
            ->exists();
    }

    /**
     * @return Collection<int, array{enrollment: StudentEnrollment, present: int, absent: int, excused: int, total: int}>
     */
    private function attendanceSummary(Collection $entries): Collection
    {
        return $entries
            ->groupBy('student_enrollment_id')
            ->map(function (Collection $enrollmentEntries): array {
                $enrollment = $enrollmentEntries->first()->enrollment;
                $totalLessons = $enrollmentEntries->sum(fn (DiaryAttendanceEntry $entry): int => (int) ($entry->record?->lesson_count ?? 1));
                $attendedLessons = $enrollmentEntries->sum('attended_lessons');
                $justifiedLessons = $enrollmentEntries->sum(function (DiaryAttendanceEntry $entry): int {
                    $record = $entry->record;
                    $isJustified = $entry->status === 'justificada'
                        || ($entry->enrollment?->attendanceJustifications
                            ?->contains(fn ($justification): bool => $justification->appliesTo($record->class_date->toDateString())) ?? false);

                    return $isJustified ? max(0, (int) $record->lesson_count - (int) $entry->attended_lessons) : 0;
                });

                return [
                    'enrollment' => $enrollment,
                    'present' => $attendedLessons,
                    'absent' => max(0, $totalLessons - $attendedLessons),
                    'excused' => $justifiedLessons,
                    'total' => $totalLessons,
                ];
            })
            ->values();
    }
}
