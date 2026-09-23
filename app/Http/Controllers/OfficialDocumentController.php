<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\OfficialDocument;
use App\Models\School;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\PdfMetadata;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OfficialDocumentController extends Controller
{
    public function create(Request $request, ?OfficialDocument $document = null): Response
    {
        abort_unless($request->user()->canManagePeople(), 403);

        if ($document) {
            abort_unless($request->user()->canManageSchool($document->school_id), 403);
        }

        return response()->view('official-documents.create', [
            'sourceDocument' => $document,
            'editorContent' => $this->sanitizeContent((string) $request->old('content_html', $document?->content_html ?? '')),
            'schools' => $this->availableSchools($request),
            'recentDocuments' => OfficialDocument::query()
                ->with(['school', 'issuedDocument'])
                ->when(! $request->user()->isAdministrator(), function ($query) use ($request): void {
                    $query->whereIn('school_id', $request->user()->manageableSchoolIds());
                })
                ->latest('id')
                ->paginate(5),
        ]);
    }

    public function store(Request $request): Response
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $availableSchoolIds = $this->availableSchools($request)->pluck('id')->all();

        $data = $request->validate([
            'school_id' => ['required', Rule::in($availableSchoolIds)],
            'type' => ['nullable', Rule::in(array_keys(OfficialDocument::TYPE_LABELS))],
            'title' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string', 'max:6000000'],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'line_spacing' => ['required', 'numeric', 'min:1', 'max:2'],
        ]);

        $school = School::query()->findOrFail($data['school_id']);
        abort_unless($request->user()->canManageSchool($school->id), 403);

        if ($message = OfficialDocumentCompliance::schoolMessage($school)) {
            throw ValidationException::withMessages(['school_id' => $message]);
        }

        $content = $this->sanitizeContent($data['content_html']);

        if (blank(strip_tags($content)) && ! str_contains($content, '<img ')) {
            throw ValidationException::withMessages([
                'content_html' => __('Digite o conteúdo do documento antes de gerar o PDF.'),
            ]);
        }

        return DB::transaction(function () use ($request, $school, $data, $content): Response {
            $officialDocument = OfficialDocument::query()->create([
                'school_id' => $school->id,
                'created_by_user_id' => $request->user()->id,
                'type' => $data['type'] ?? OfficialDocument::TYPE_OTHER,
                'title' => $data['title'],
                'content_html' => $content,
                'paper_size' => 'a4',
                'orientation' => $data['orientation'],
                'line_spacing' => $data['line_spacing'],
            ]);

            $issuedDocument = $this->issuedDocument($request, $officialDocument);
            $officialDocument->update(['issued_document_id' => $issuedDocument->id]);

            return $this->archivedPdf($officialDocument);
        });
    }

    public function reissue(Request $request, OfficialDocument $document): Response
    {
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($document->school_id), 403);

        return DB::transaction(function () use ($document): Response {
            $document = OfficialDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($document->issuedDocument, 404);

            return $this->archivedPdf($document);
        });
    }

    private function archivedPdf(OfficialDocument $document): Response
    {
        $issued = $document->issuedDocument;
        $disk = Storage::disk('local');
        if ($issued->file_path) {
            // Never silently replace an archived generation with a fresh render.
            abort_unless($disk->exists($issued->file_path), 404);
            $bytes = $disk->get($issued->file_path);
        } else {
            $pdf = Pdf::loadView('official-documents.pdf', [
                'officialDocument' => $document->load('school'),
                'issuedDocument' => $issued,
                'verificationUrl' => route('documents.verify', $issued->verification_code),
                'letterhead' => PdfLetterhead::make($document->school),
            ])->setPaper('a4', $document->orientation);
            $response = PdfMetadata::stream($pdf, $this->filename($document), $document->title.' - Beabá');
            $bytes = $response->getContent();
            $path = 'issued-documents/'.$issued->uuid.'.pdf';
            abort_unless($disk->put($path, $bytes), 500);
            $issued->update(['file_path' => $path]);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($document).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function issuedDocument(Request $request, OfficialDocument $officialDocument): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'official-document',
            'person_id' => $request->user()->person_id,
            'school_id' => $officialDocument->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $officialDocument->title,
                'official_document_id' => $officialDocument->id,
                'official_document_type' => $officialDocument->type,
                'official_document_type_label' => $officialDocument->typeLabel(),
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

    private function filename(OfficialDocument $document): string
    {
        return 'beaba-documento-'.Str::slug($document->title).'-'.($document->issuedDocument?->issued_at ?? $document->created_at)->format('Ymd-His').'.pdf';
    }

    private function sanitizeContent(string $html): string
    {
        return app(\App\Support\OfficialDocumentContent::class)->sanitize($html);
    }

    private function availableSchools(Request $request)
    {
        return School::query()
            ->where('active', true)
            ->when(! $request->user()->isAdministrator(), function ($query) use ($request): void {
                $query->whereIn('id', $request->user()->manageableSchoolIds());
            })
            ->orderBy('name')
            ->get();
    }
}
