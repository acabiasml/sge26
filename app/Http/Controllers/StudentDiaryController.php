<?php

namespace App\Http\Controllers;

use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\IssuedDocument;
use App\Models\SchoolClassScheduleSlot;
use App\Models\StudentBehaviorGrade;
use App\Models\StudentEnrollment;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentDiaryController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = StudentEnrollment::query()
            ->with(['schoolClass.academicYear.school', 'schoolClass.courses.components.area'])
            ->where('person_id', $request->user()->person_id)
            ->get();

        return view('student-diaries.index', compact('enrollments'));
    }

    public function show(Request $request, StudentEnrollment $enrollment, CurriculumComponent $component): View
    {
        abort_unless($enrollment->person_id === $request->user()->person_id, 403);
        $enrollment->load('schoolClass.academicYear.school.concepts', 'schoolClass.courses');
        abort_unless($enrollment->schoolClass->courses->contains('id', $component->academic_course_id), 404);

        $academicYear = $enrollment->schoolClass->academicYear;
        $periods = $academicYear->periods()->orderBy('position')->get();
        $assessments = DiaryAssessment::query()->with(['results' => fn ($query) => $query->where('student_enrollment_id', $enrollment->id)])
            ->where('school_class_id', $enrollment->school_class_id)->where('curriculum_component_id', $component->id)->orderBy('assessment_date')->get();
        $attendance = DiaryAttendanceRecord::query()->with(['entries' => fn ($query) => $query->where('student_enrollment_id', $enrollment->id)])
            ->where('school_class_id', $enrollment->school_class_id)->where('curriculum_component_id', $component->id)->orderBy('class_date')->get();
        $contents = DiaryContent::query()->where('school_class_id', $enrollment->school_class_id)->where('curriculum_component_id', $component->id)->orderBy('class_date')->get();
        $behaviorGrades = StudentBehaviorGrade::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereIn('academic_period_id', $periods->pluck('id'))
            ->get()
            ->keyBy('academic_period_id');

        return view('student-diaries.show', compact('enrollment', 'component', 'academicYear', 'periods', 'assessments', 'attendance', 'contents', 'behaviorGrades'));
    }

    public function schedule(Request $request, StudentEnrollment $enrollment): View
    {
        $this->authorizeEnrollment($request, $enrollment);
        $enrollment->load([
            'student',
            'schoolClass.academicYear.school',
            'schoolClass.schedules.slots.componentAssignment.component.course',
            'schoolClass.schedules.slots.componentAssignment.teacher',
            'courses',
        ]);

        return view('student-diaries.schedule', [
            'enrollment' => $enrollment,
            'weekdays' => SchoolClassScheduleSlot::WEEKDAY_LABELS,
        ]);
    }

    public function schedulePdf(Request $request, StudentEnrollment $enrollment): Response
    {
        $this->authorizeEnrollment($request, $enrollment);
        $enrollment->load([
            'student',
            'schoolClass.academicYear.school',
            'schoolClass.schedules.slots.componentAssignment.component.course',
            'schoolClass.schedules.slots.componentAssignment.teacher',
            'courses',
        ]);
        $academicYear = $enrollment->schoolClass->academicYear;
        $issuedDocument = $this->issuedScheduleDocument($request, $enrollment);

        $pdf = Pdf::loadView('reports.student-schedule', [
            'enrollment' => $enrollment,
            'academicYear' => $academicYear,
            'weekdays' => SchoolClassScheduleSlot::WEEKDAY_LABELS,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-horario-estudante-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function authorizeEnrollment(Request $request, StudentEnrollment $enrollment): void
    {
        abort_unless($enrollment->person_id === $request->user()->person_id, 403);
    }

    private function issuedScheduleDocument(Request $request, StudentEnrollment $enrollment): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'student-schedule',
            'person_id' => $request->user()->person_id,
            'school_id' => $enrollment->schoolClass?->academicYear?->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Horário do estudante',
                'student_enrollment_id' => $enrollment->id,
                'school_class_id' => $enrollment->school_class_id,
            ],
            'issued_at' => now(),
        ]);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
