<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentReportCardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentReportCardController extends Controller
{
    public function show(Request $request, StudentEnrollment $enrollment, StudentReportCardBuilder $builder): View
    {
        $this->authorizeReportCard($request, $enrollment);

        return view('student-report-cards.show', [
            'report' => $builder->build($enrollment),
            'scoreView' => $this->scoreView($request, $enrollment),
            'canChooseScoreView' => $this->canChooseScoreView($request, $enrollment),
        ]);
    }

    public function pdf(Request $request, StudentEnrollment $enrollment, StudentReportCardBuilder $builder): Response|RedirectResponse
    {
        $this->authorizeReportCard($request, $enrollment);

        $report = $builder->build($enrollment);

        if ($message = $this->complianceMessage($request, $report)) {
            return redirect()->route('enrollments.report-card.show', $enrollment)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, $enrollment, $report, 'student-report-card', 'Boletim escolar');

        $pdf = Pdf::loadView('reports.student-report-card', [
            'report' => $report,
            'scoreView' => $this->scoreView($request, $enrollment),
            'canChooseScoreView' => $this->canChooseScoreView($request, $enrollment),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($report['academicYear']->school),
        ])->setPaper('a4', 'portrait');

        return \App\Support\PdfMetadata::stream($pdf, 'beaba-boletim-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function individualRecordPdf(Request $request, StudentEnrollment $enrollment, StudentReportCardBuilder $builder): Response|RedirectResponse
    {
        $this->authorizeReportCard($request, $enrollment);

        $report = $builder->build($enrollment);

        if ($message = $this->complianceMessage($request, $report)) {
            return redirect()->route('enrollments.report-card.show', $enrollment)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, $enrollment, $report, 'student-individual-record', 'Ficha individual');

        $pdf = Pdf::loadView('reports.student-individual-record', [
            'report' => $report,
            'scoreView' => $this->scoreView($request, $enrollment),
            'canChooseScoreView' => $this->canChooseScoreView($request, $enrollment),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($report['academicYear']->school),
        ])->setPaper('a4', 'portrait');

        return \App\Support\PdfMetadata::stream($pdf, 'beaba-ficha-individual-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function authorizeReportCard(Request $request, StudentEnrollment $enrollment): void
    {
        $enrollment->loadMissing('schoolClass.academicYear');
        $academicYear = $enrollment->schoolClass?->academicYear;

        abort_unless($academicYear, 404);
        abort_unless(
            $request->user()->person_id === $enrollment->person_id
                || $request->user()->canManageSchool($academicYear->school_id),
            403
        );
    }

    private function scoreView(Request $request, StudentEnrollment $enrollment): string
    {
        if ($request->user()->person_id === $enrollment->person_id) {
            return 'conceitos';
        }

        return $request->query('notas') === 'conceitos' ? 'conceitos' : 'numeros';
    }

    private function canChooseScoreView(Request $request, StudentEnrollment $enrollment): bool
    {
        return $request->user()->person_id !== $enrollment->person_id;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function complianceMessage(Request $request, array $report): ?string
    {
        return OfficialDocumentCompliance::studentMessage($report['student'], $request->boolean('confirm_missing_student_cpf'))
            ?? OfficialDocumentCompliance::schoolMessage($report['academicYear']->school);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function issuedDocument(Request $request, StudentEnrollment $enrollment, array $report, string $type, string $title): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $enrollment->person_id,
            'school_id' => $report['academicYear']->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $title.' - '.$report['student']->full_name,
                'student_enrollment_id' => $enrollment->id,
                'school_class_id' => $report['schoolClass']->id,
                'academic_year_id' => $report['academicYear']->id,
                'score_view' => $this->scoreView($request, $enrollment),
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
