<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Support\CredentialRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CredentialArchiveController extends Controller
{
    public function destroy(Request $request, IssuedDocument $document)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        abort_unless(in_array($document->type, IssuedDocument::CREDENTIAL_TYPES, true), 404);
        $personId = $document->person_id;
        DB::transaction(function () use ($document): void {
            $document = IssuedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $path = $document->file_path;
            $document->delete();
            if ($path && Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                throw new \RuntimeException('Não foi possível excluir o arquivo original. A exclusão foi cancelada.');
            }
        });

        $redirect = $personId ? redirect()->to(route('people.show', $personId).'#section-certificados') : redirect()->route('document-issuance.index');

        return $redirect->with('status', 'Documento excluído. A exclusão foi registrada na auditoria.');
    }

    public function pdf(Request $request, IssuedDocument $document)
    {
        $this->authorizeDocument($request, $document);

        return response($this->bytes($document), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="beaba-documento-'.$document->uuid.'.pdf"']);
    }

    public function batch(Request $request, string $batch)
    {
        return view('free-courses.issued', ['documents' => $this->documents($request, $batch)->load('registration'), 'batch' => $batch]);
    }

    public function zip(Request $request, string $batch)
    {
        $documents = $this->documents($request, $batch);
        $path = tempnam(sys_get_temp_dir(), 'beaba-cert-');
        $opened = false;
        try {
            $zip = new \ZipArchive;
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Não foi possível preparar os arquivos.');
            }
            $opened = true;
            foreach ($documents as $document) {
                $zip->addFromString('certificado-'.Str::slug($document->payload['participant_name']).'-'.$document->verification_code.'.pdf', $this->bytes($document));
            }
            $zip->close();
            $opened = false;
            $bytes = file_get_contents($path);
        } finally {
            if ($opened) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
        }

        return response($bytes, 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="certificados-'.$batch.'.zip"']);
    }

    private function documents(Request $request, string $batch)
    {
        abort_unless(Str::isUuid($batch), 404);
        $documents = IssuedDocument::where('batch_uuid', $batch)->orderBy('id')->get();
        abort_if($documents->isEmpty(), 404);
        foreach ($documents as $document) {
            $this->authorizeDocument($request, $document);
        }

        return $documents;
    }

    private function authorizeDocument(Request $request, IssuedDocument $document): void
    {
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($document->school_id), 403);
        abort_unless(in_array($document->type, IssuedDocument::CREDENTIAL_TYPES, true), 404);
        abort_if($document->revoked_at, 410);
    }

    private function bytes(IssuedDocument $document): string
    {
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404, 'O arquivo original não está disponível.');
        $bytes = Storage::disk('local')->get($document->file_path);
        abort_if($document->file_sha256 && ! hash_equals($document->file_sha256, hash('sha256', $bytes)), 409, 'O arquivo não corresponde ao original arquivado.');

        return $bytes;
    }
}
