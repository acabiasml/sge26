<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\CredentialRegistry;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\SchoolRecordPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AcademicYearFinalResultsPdfController extends Controller
{
    public function prepare(Request $request, AcademicYear $academicYear, CredentialRegistry $registry)
    {
        $registry->authorize($request->user(), $academicYear->school_id);
        $school = $academicYear->school;

        return view('credential-registry.prepare-record', [
            'school' => $school, 'title' => 'Ata consolidada de resultados finais',
            'context' => $academicYear->name,
            'preview' => route('academic-years.final-results.pdf', ['academicYear' => $academicYear, 'preview' => 1]),
            'action' => route('academic-years.final-results.register', $academicYear),
            'books' => $registry->digitalBooks($request->user(), $school, 'final-results')->get(),
            'notice' => 'Confira os resultados de todas as turmas antes de registrar a ata consolidada do ano letivo.',
        ]);
    }

    public function __invoke(Request $request, AcademicYear $academicYear, SchoolRecordPdf $records): Response
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $academicYear->load([
            'school',
            'classes.courses',
            'classes.enrollments.student',
            'classes.enrollments.courses',
            'classes.enrollments.finalResultCalculatedBy',
        ]);

        $classes = $academicYear->classes
            ->sortBy('name')
            ->values()
            ->map(function ($class): array {
                $enrollments = $class->enrollments
                    ->sortBy(fn (StudentEnrollment $enrollment): string => $enrollment->student?->full_name ?? '')
                    ->values();

                return [
                    'class' => $class,
                    'enrollments' => $enrollments,
                    'counts' => $enrollments
                        ->groupBy(fn (StudentEnrollment $enrollment): string => $enrollment->finalResultLabel())
                        ->map->count()
                        ->sortKeys(),
                ];
            });

        $rowsCount = $classes->sum(fn (array $classSummary): int => $classSummary['enrollments']->count());

        $type = 'academic-year-final-results';
        $official = $request->isMethod('post');
        $preview = ! $official && $request->boolean('preview');
        $payload = [
            'title' => ($official ? 'Ata consolidada de resultados finais - ' : 'Resultados finais - ').$academicYear->name,
            'academic_year_id' => $academicYear->id,
            'rows_count' => $rowsCount,
        ];
        $stages = $academicYear->classes->flatMap->courses->pluck('stage')->all();
        $render = function (IssuedDocument $document) use ($academicYear, $classes, $rowsCount, $stages, $official, $preview) {
            $pdf = Pdf::loadView('reports.academic-year-final-results', [
                'academicYear' => $academicYear,
                'classes' => $classes,
                'rowsCount' => $rowsCount,
                'issuedDocument' => $document,
                'verificationUrl' => $preview ? null : route('documents.verify', $document->verification_code),
                'letterhead' => PdfLetterhead::make($academicYear->school, $stages),
                'official' => $official,
                'isDraft' => $preview,
            ])->setPaper('a4', 'landscape');

            return \App\Support\PdfMetadata::stream($pdf, 'beaba-resultados-finais-ano-letivo-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
        };
        if ($preview) {
            return $render($records->draft($request->user(), $academicYear->school, $type, null, $payload));
        }
        if ($official) {
            $school = $academicYear->school;
            $data = $request->validate(SchoolRecordPdf::rules($school));
            $enrollments = $classes->flatMap(fn ($class) => $class['enrollments']);
            if (! $academicYear->isClosed() || $enrollments->isEmpty()
                || $enrollments->contains(fn ($enrollment) => in_array($enrollment->final_result_status, [null, StudentEnrollment::FINAL_PENDING], true))) {
                throw ValidationException::withMessages(['document' => 'Encerre o ano letivo e confira os resultados de todas as matrículas antes de registrar a ata consolidada.']);
            }
            if ($message = OfficialDocumentCompliance::schoolMessage($school) ?? OfficialDocumentCompliance::authorizationMessage($school, $stages)) {
                throw ValidationException::withMessages(['document' => $message]);
            }
            $staff = $records->signers($school);
            $payload['signers'] = ['director' => $staff['diretor']->full_name, 'secretary' => $staff['secretário']->full_name];
            $document = $records->issue($request->user(), $school, $type, null, $payload, $data, $render);

            return redirect()->route('credential-registry.entry', $document->registration);
        }

        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => $payload,
            'issued_at' => now(),
        ]);

        return $render($issuedDocument);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
