<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\SchoolClass;
use App\Support\AcademicContextLabel;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClassFinalResultsPdfController extends Controller
{
    public function __invoke(Request $request, SchoolClass $class): Response
    {
        $class->load([
            'academicYear.school',
            'courses',
            'enrollments.student',
            'enrollments.courses',
            'enrollments.finalResultCalculatedBy',
        ]);

        $academicYear = $class->academicYear;
        abort_unless($academicYear, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $enrollments = $class->enrollments
            ->sortBy(fn ($enrollment): string => $enrollment->student?->full_name ?? '')
            ->values();

        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'class-final-results',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Ata de resultados finais - '.AcademicContextLabel::classWithStages($class->name, $class->courses),
                'school_class_id' => $class->id,
                'academic_year_id' => $academicYear->id,
                'rows_count' => $enrollments->count(),
            ],
            'issued_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.class-final-results', [
            'schoolClass' => $class,
            'academicYear' => $academicYear,
            'enrollments' => $enrollments,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-resultados-finais-turma-'.$class->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
