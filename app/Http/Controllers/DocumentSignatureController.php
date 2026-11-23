<?php

namespace App\Http\Controllers;

use App\Models\DocumentSignatureVersion;
use App\Models\IssuedDocument;
use App\Models\OfficialDocument;
use App\Support\CredentialRegistry;
use App\Support\PdfSignatureEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentSignatureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $documents = IssuedDocument::where(function ($query) use ($user) {
            $query->where('issued_by_user_id', $user->id);
            if ($user->canManagePeople()) {
                $query->orWhere(fn ($history) => $history->where('type', 'student-academic-history')
                    ->when(! $user->isAdministrator(), fn ($q) => $q->whereIn('school_id', $user->manageableSchoolIds())));
            }
        })->whereDoesntHave('registration')
            ->when(! $user->isAdministrator(), fn ($query) => $query->where(fn ($q) => $q->whereNull('school_id')->orWhereIn('school_id', $user->visibleSchoolIds())))
            ->whereNotIn('type', IssuedDocument::CREDENTIAL_TYPES)
            ->whereNotNull('file_sha256')->whereNotNull('file_path')->whereNull('revoked_at')
            ->where(function ($query) use ($request) {
                $query->where('type', '!=', 'official-document')
                    ->orWhereIn('id', OfficialDocument::query()->select('issued_document_id')
                        ->where(fn ($q) => $q->where('visibility', OfficialDocument::VISIBILITY_PUBLIC)
                            ->orWhere('private_owner_user_id', $request->user()->id)));
            })->withCount('signatureVersions')->latest('id')->paginate(10);

        return view('document-signatures.index', compact('documents'));
    }

    public function show(Request $request, IssuedDocument $document)
    {
        $this->authorizeDocument($request, $document);
        $versions = $document->signatureVersions()->latest('id')->paginate(10);
        $requiredSigners = array_values(array_unique($document->payload['signers'] ?? []));

        return view('document-signatures.show', compact('document', 'versions', 'requiredSigners'));
    }

    public function original(Request $request, IssuedDocument $document, CredentialRegistry $registry)
    {
        $this->authorizeDocument($request, $document);

        return $this->pdf($registry->bytes($document->file_path, $document->file_sha256), 'original-'.$document->verification_code);
    }

    public function upload(Request $request, IssuedDocument $document, CredentialRegistry $registry)
    {
        $this->authorizeDocument($request, $document);
        $requiredSigners = array_values(array_unique($document->payload['signers'] ?? []));
        $data = $request->validate([
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'validation_report' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'signers' => $requiredSigners ? ['required', 'array', 'min:1'] : ['required', 'string', 'max:500'],
            'signers.*' => [Rule::in($requiredSigners), 'distinct'],
            'complete' => [Rule::requiredIf(! $requiredSigners), 'nullable', 'boolean'],
            'reviewed' => ['accepted'],
        ]);
        $signers = $requiredSigners ? implode(' · ', $data['signers']) : $data['signers'];
        $complete = $requiredSigners ? count($data['signers']) === count($requiredSigners) : (bool) $data['complete'];
        $signatureCount = $requiredSigners ? count($data['signers']) : 1;
        $signed = $request->file('signed_pdf')->get();
        $report = $request->file('validation_report')->get();
        $digest = hash('sha256', $signed);
        if (hash_equals($document->file_sha256, $digest) || hash_equals(hash('sha256', $report), $digest)) {
            throw ValidationException::withMessages(['signed_pdf' => 'Selecione a versão assinada e seu relatório de validação em arquivos distintos.']);
        }
        $paths = [];
        try {
            DB::transaction(function () use ($document, $request, $signers, $complete, $signatureCount, $signed, $report, $digest, $registry, &$paths) {
                $document = IssuedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $this->authorizeDocument($request, $document);
                $original = $registry->bytes($document->file_path, $document->file_sha256);
                app(PdfSignatureEvidence::class)->confirm($signed, $signatureCount, $original);
                if ($document->signatureVersions()->where('signed_sha256', $digest)->exists()) {
                    throw ValidationException::withMessages(['signed_pdf' => 'Esta versão assinada já está arquivada.']);
                }
                $prefix = 'document-signatures/'.$document->uuid.'/'.Str::uuid();
                foreach ([$prefix.'-assinado.pdf' => $signed, $prefix.'-validacao.pdf' => $report] as $path => $bytes) {
                    $paths[] = $path;
                    throw_unless(Storage::disk('local')->put($path, $bytes), \RuntimeException::class, 'Não foi possível arquivar o PDF.');
                    chmod(Storage::disk('local')->path($path), 0600);
                }
                $document->signatureVersions()->create([
                    'checked_by_user_id' => $request->user()->id, 'signed_path' => $paths[0], 'signed_sha256' => $digest,
                    'report_path' => $paths[1], 'report_sha256' => hash('sha256', $report), 'original_sha256' => $document->file_sha256,
                    'signers' => $signers, 'complete' => $complete,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return back()->with('status', 'Versão assinada e relatório de validação externa arquivados.');
    }

    public function download(Request $request, DocumentSignatureVersion $version, string $file, CredentialRegistry $registry)
    {
        $this->authorizeDocument($request, $version->document);
        abort_unless(in_array($file, ['assinado', 'validacao'], true), 404);
        $bytes = $file === 'assinado' ? $registry->bytes($version->signed_path, $version->signed_sha256)
            : $registry->bytes($version->report_path, $version->report_sha256);

        return $this->pdf($bytes, 'beaba-'.$file.'-'.$version->id);
    }

    private function authorizeDocument(Request $request, IssuedDocument $document): void
    {
        $user = $request->user();
        $institutionalHistory = $document->type === 'student-academic-history' && $document->school_id
            && $user->canManagePeople($document->school_id);
        abort_unless($document->issued_by_user_id === $user->id || $institutionalHistory, 403);
        abort_if($document->registration()->exists(), 404);
        abort_unless($user->isAdministrator() || $document->school_id === null || in_array($document->school_id, $user->visibleSchoolIds(), true), 403);
        abort_unless(! in_array($document->type, IssuedDocument::CREDENTIAL_TYPES, true) && $document->file_path && $document->file_sha256, 404);
        abort_if($document->revoked_at, 410);
        if ($document->type === 'official-document') {
            $source = OfficialDocument::where('issued_document_id', $document->id)->firstOrFail();
            abort_unless($source->visibility === OfficialDocument::VISIBILITY_PUBLIC || $source->private_owner_user_id === $request->user()->id, 403);
        }
    }

    private function pdf(string $bytes, string $name)
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }
}
