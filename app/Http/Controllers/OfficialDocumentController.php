<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\OfficialDocument;
use App\Models\School;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OfficialDocumentController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return response()->view('official-documents.create', [
            'schools' => $this->availableSchools($request),
            'recentDocuments' => OfficialDocument::query()
                ->with(['school', 'issuedDocument'])
                ->when(! $request->user()->isAdministrator(), function ($query) use ($request): void {
                    $query->whereIn('school_id', $request->user()->manageableSchoolIds());
                })
                ->latest()
                ->limit(8)
                ->get(),
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
            'content_html' => ['required', 'string', 'max:200000'],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'line_spacing' => ['required', 'numeric', 'min:1', 'max:2'],
        ]);

        $school = School::query()->findOrFail($data['school_id']);
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $content = $this->sanitizeContent($data['content_html']);

        if (blank(strip_tags($content))) {
            throw ValidationException::withMessages([
                'content_html' => 'Digite o conteúdo do documento antes de gerar o PDF.',
            ]);
        }

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

        $pdf = Pdf::loadView('official-documents.pdf', [
            'officialDocument' => $officialDocument->load('school'),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', $data['orientation']);

        return $pdf->download($this->filename($officialDocument));
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
        return 'beaba-documento-'.Str::slug($document->title).'-'.now()->format('Ymd-His').'.pdf';
    }

    private function sanitizeContent(string $html): string
    {
        $html = preg_replace('/<\/?(script|style|iframe|object|embed|link|meta)[^>]*>/i', '', $html) ?? '';
        $html = strip_tags($html, '<p><div><br><span><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td>');
        $html = preg_replace_callback('/<([a-z][a-z0-9]*)(\s+[^>]*)?>/i', function (array $matches): string {
            $tag = strtolower($matches[1]);
            $attributes = $matches[2] ?? '';
            $style = $this->sanitizeStyle($attributes);

            if ($style === '' || ! in_array($tag, ['span', 'p', 'div', 'h2', 'h3', 'h4', 'th', 'td'], true)) {
                return '<'.$tag.'>';
            }

            return '<'.$tag.' style="'.htmlspecialchars($style, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        }, $html) ?? '';

        return trim($html);
    }

    private function sanitizeStyle(string $attributes): string
    {
        if (! preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attributes, $match)) {
            return '';
        }

        $allowed = [];
        $fontFamilies = ['DejaVu Sans', 'DejaVu Serif', 'DejaVu Sans Mono'];

        $style = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            $property = strtolower(trim((string) $property));
            $value = trim((string) $value, " \t\n\r\0\x0B\"'");

            if ($property === 'font-family' && in_array($value, $fontFamilies, true)) {
                $allowed[] = 'font-family: '.$value;
            }

            if ($property === 'font-size' && preg_match('/^(10|11|12|14|16|18)pt$/', $value)) {
                $allowed[] = 'font-size: '.$value;
            }

            if ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                $allowed[] = 'text-align: '.$value;
            }
        }

        return implode('; ', $allowed);
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
