<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AcademicYearFinalResultsPdfController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear): Response
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

        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'academic-year-final-results',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Resultados finais - '.$academicYear->name,
                'academic_year_id' => $academicYear->id,
                'rows_count' => $rowsCount,
            ],
            'issued_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.academic-year-final-results', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'rowsCount' => $rowsCount,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('beaba-resultados-finais-ano-letivo-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
