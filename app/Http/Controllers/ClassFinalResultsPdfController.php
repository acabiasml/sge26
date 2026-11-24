<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Support\AcademicContextLabel;
use App\Support\CredentialRegistry;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\SchoolRecordPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ClassFinalResultsPdfController extends Controller
{
    public function prepare(Request $request, SchoolClass $class, CredentialRegistry $registry)
    {
        $class->load('academicYear.school');
        $registry->authorize($request->user(), $class->academicYear->school_id);

        return view('credential-registry.prepare-record', [
            'school' => $class->academicYear->school, 'title' => 'Ata de resultados finais',
            'context' => $class->name.' · '.$class->academicYear->name,
            'preview' => route('classes.final-results.pdf', $class),
            'action' => route('classes.final-results.register', $class),
            'books' => $registry->digitalBooks($request->user(), $class->academicYear->school, 'final-results')->get(),
            'notice' => 'Registre a ata após o encerramento do ano letivo e a conferência dos resultados. A via registrada fica preservada para consulta e retificação.',
        ]);
    }

    public function __invoke(Request $request, SchoolClass $class): Response
    {
        $class->load([
            'academicYear.school',
            'courses',
            'enrollments.student.contacts',
            'enrollments.courses',
            'enrollments.finalResultCalculatedBy',
        ]);

        $academicYear = $class->academicYear;
        abort_unless($academicYear, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $enrollments = $class->enrollments
            ->sortBy(fn ($enrollment): string => $enrollment->student?->full_name ?? '')
            ->values();

        $records = app(SchoolRecordPdf::class);
        $payload = [
            'title' => 'Ata de resultados finais - '.AcademicContextLabel::classWithStages($class->name, $class->courses),
            'school_class_id' => $class->id, 'academic_year_id' => $academicYear->id, 'rows_count' => $enrollments->count(),
        ];
        $render = function (IssuedDocument $document) use ($class, $academicYear, $enrollments) {
            $pdf = Pdf::loadView('reports.class-final-results', [
                'schoolClass' => $class, 'academicYear' => $academicYear, 'enrollments' => $enrollments,
                'issuedDocument' => $document, 'verificationUrl' => route('documents.verify', $document->verification_code),
                'letterhead' => PdfLetterhead::forClass($class),
            ])->setPaper('a4', 'landscape');

            return \App\Support\PdfMetadata::stream($pdf, 'beaba-resultados-finais-turma-'.$class->id.'.pdf');
        };
        if ($request->isMethod('get')) {
            return $render($records->draft($request->user(), $academicYear->school, 'class-final-results', null, $payload));
        }
        $data = $request->validate(SchoolRecordPdf::rules($academicYear->school));
        if (! $academicYear->isClosed() || $enrollments->isEmpty()
            || $enrollments->contains(fn ($enrollment) => in_array($enrollment->final_result_status, [null, StudentEnrollment::FINAL_PENDING], true))) {
            throw ValidationException::withMessages(['document' => 'Encerre o ano letivo e confira os resultados de todas as matrículas antes de registrar a ata.']);
        }
        if ($message = OfficialDocumentCompliance::schoolMessage($academicYear->school)
            ?? OfficialDocumentCompliance::courseAuthorizationMessage($academicYear->school, $class->courses)) {
            throw ValidationException::withMessages(['document' => $message]);
        }
        $staff = $records->signers($academicYear->school);
        $payload['signers'] = ['director' => $staff['diretor']->full_name, 'secretary' => $staff['secretário']->full_name];
        $document = $records->issue($request->user(), $academicYear->school, 'class-final-results', null, $payload, $data, $render);

        return redirect()->route('credential-registry.entry', $document->registration);
    }
}
