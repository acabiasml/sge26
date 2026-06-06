<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Models\IssuedDocument;
use App\Models\School;
use App\Support\DocumentVerificationPresenter;
use App\Support\PdfLetterhead;
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

        $report = ReportDefinition::make($type, $request->user(), $this->tableFilters($request), $this->tableSearch($request));

        return Excel::download(new GenericReportExport($report), $this->filename($report->type, 'xlsx'));
    }

    public function pdf(Request $request, string $type): Response
    {
        abort_unless($request->user()->canManagePeople() || $request->user()->canManageSchools(), 403);

        $report = ReportDefinition::make($type, $request->user(), $this->tableFilters($request), $this->tableSearch($request));
        $school = $this->schoolForReport($report);
        $issuedDocument = $this->issuedDocument($request, $report, $school);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($report->type, 'pdf'));
    }

    public function verify(string $code): Response
    {
        $document = IssuedDocument::query()
            ->with(['issuedBy.person', 'school'])
            ->where('verification_code', $code)
            ->firstOrFail();

        return response()->view('reports.verify', [
            'document' => $document,
            'verification' => DocumentVerificationPresenter::make($document),
        ]);
    }

    public function verifyForm(): Response
    {
        return response()->view('reports.verify-form');
    }

    public function lookup(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $code = strtoupper(trim((string) $data['code']));

        return redirect()->route('documents.verify', $code);
    }

    private function issuedDocument(Request $request, ReportDefinition $report, ?School $school = null): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'report:'.$report->type,
            'person_id' => $request->user()->person_id,
            'school_id' => $school?->id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $report->title,
                'headings' => $report->headings,
                'rows_count' => $report->rows->count(),
                'filters' => $report->filters,
                'search' => $report->search,
            ],
            'issued_at' => now(),
        ]);
    }

    private function schoolForReport(ReportDefinition $report): ?School
    {
        $schoolFilter = $report->filters['escola'] ?? null;

        if (is_numeric($schoolFilter)) {
            return School::query()->find((int) $schoolFilter);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableFilters(Request $request): array
    {
        $filters = $request->query('table-filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        if ($filters === [] && is_string($request->server('QUERY_STRING'))) {
            parse_str($request->server('QUERY_STRING'), $query);
            $filters = $query['table-filters'] ?? [];
        }

        if (! is_array($filters)) {
            return [];
        }

        return collect($filters)
            ->filter(fn ($value): bool => is_scalar($value) && filled((string) $value))
            ->mapWithKeys(fn ($value, string $key): array => [$this->tableFilterKey($key) => (string) $value])
            ->all();
    }

    private function tableFilterKey(string $key): string
    {
        return (string) Str::of($key)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_');
    }

    private function tableSearch(Request $request): ?string
    {
        $search = $request->query('table-search');

        return is_string($search) && filled($search) ? $search : null;
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
