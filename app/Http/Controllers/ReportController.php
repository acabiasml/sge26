<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Models\IssuedDocument;
use App\Support\Reports\ReportDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function excel(Request $request, string $type): BinaryFileResponse
    {
        abort_unless($request->user()->canManagePeople() || $request->user()->canManageSchools(), 403);

        $report = ReportDefinition::make($type, $request->user());

        return Excel::download(new GenericReportExport($report), $this->filename($report->type, 'xlsx'));
    }

    public function pdf(Request $request, string $type): Response
    {
        abort_unless($request->user()->canManagePeople() || $request->user()->canManageSchools(), 403);

        $report = ReportDefinition::make($type, $request->user());
        $issuedDocument = $this->issuedDocument($request, $report);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($report->type, 'pdf'));
    }

    public function verify(string $code): Response
    {
        $document = IssuedDocument::query()
            ->with('issuedBy')
            ->where('verification_code', $code)
            ->firstOrFail();

        return response()->view('reports.verify', [
            'document' => $document,
        ]);
    }

    private function issuedDocument(Request $request, ReportDefinition $report): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'report:'.$report->type,
            'person_id' => $request->user()->person_id,
            'school_id' => null,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $report->title,
                'headings' => $report->headings,
                'rows_count' => $report->rows->count(),
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

    private function filename(string $type, string $extension): string
    {
        return 'beaba-'.$type.'-'.now()->format('Ymd-His').'.'.$extension;
    }
}
