<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnrollmentPdfController extends Controller
{
    public function __invoke(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        $class = $enrollment->schoolClass()->firstOrFail();
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($enrollment->school_class_id === $class->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $enrollment->load(['student.contacts', 'courses', 'schoolClass.courses', 'enrolledBy', 'transferredBy', 'reclassifiedFrom.schoolClass', 'reclassifiedFrom.courses']);
        $academicYear->load('school');

        if ($message = OfficialDocumentCompliance::personMessage($enrollment->student)) {
            return redirect()->route('classes.enrollments.index', $class)->with('status', $message);
        }

        if ($message = OfficialDocumentCompliance::schoolMessage($academicYear->school)) {
            return redirect()->route('schools.edit', $academicYear->school)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, $academicYear, $enrollment);

        $pdf = Pdf::loadView('reports.records.enrollment', [
            'academicYear' => $academicYear,
            'class' => $class->load('courses'),
            'enrollment' => $enrollment,
            'issuedDocument' => $issuedDocument,
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-ficha-matricula-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function issuedDocument(Request $request, AcademicYear $academicYear, StudentEnrollment $enrollment): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'student-enrollment',
            'person_id' => $enrollment->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Ficha de matrícula',
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $enrollment->school_class_id,
                'student_enrollment_id' => $enrollment->id,
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
