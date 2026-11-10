<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\PdfMetadata;
use App\Support\StudentSocioeconomicPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentSocioeconomicController extends Controller
{
    public function pdf(Request $request, StudentEnrollment $enrollment)
    {
        $enrollment->loadMissing(['schoolClass.academicYear.school', 'courses', 'student.contacts']);
        $year = $enrollment->schoolClass?->academicYear;
        abort_unless($year?->school, 404);
        abort_unless($request->user()->canManageSchool($year->school_id), 403);
        $student = $enrollment->student;
        if ($message = OfficialDocumentCompliance::studentMessage($student, $request->boolean('confirm_missing_student_cpf'))
            ?? OfficialDocumentCompliance::schoolMessage($year->school)) {
            return redirect()->route('document-issuance.index')->with('status', $message);
        }

        // O verso contém somente as matrículas do estudante nesta escola.
        $enrollments = $student->studentEnrollments()->with(['schoolClass.academicYear', 'courses'])
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('school_id', $year->school_id))
            ->orderBy('enrolled_at')->orderBy('id')->get();
        $issuedDocument = IssuedDocument::create([
            'uuid' => (string) Str::uuid(), 'verification_code' => 'BEABA-'.Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4)),
            'type' => 'student-socioeconomic', 'person_id' => $student->id, 'school_id' => $year->school_id,
            'issued_by_user_id' => $request->user()->id,
            // A consulta pública do QR Code não recebe renda, saúde ou consentimentos.
            'payload' => ['title' => 'Ficha socioeconômica e termo de concessão de benefícios', 'student_enrollment_id' => $enrollment->id, 'academic_year_id' => $year->id],
            'issued_at' => now(),
        ]);
        $pdf = app(StudentSocioeconomicPdf::class)->make([
            'student' => $student, 'enrollment' => $enrollment, 'academicYear' => $year,
            'enrollments' => $enrollments, 'profile' => $student->socioeconomic_data ?? [],
            'guardian' => $student->contacts->firstWhere('legal_guardian', true),
            'issuedDocument' => $issuedDocument, 'letterhead' => PdfLetterhead::make($year->school),
        ]);

        return PdfMetadata::stream($pdf, 'beaba-ficha-socioeconomica-'.$student->id.'-'.now()->format('Ymd-His').'.pdf');
    }
}
