<?php

namespace App\Support;

use App\Models\CredentialBook;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

final class CredentialBookArchive
{
    public function __construct(private CredentialRegistry $registry) {}

    public function pdf(CredentialBook $book, ?User $user)
    {
        return DB::transaction(function () use ($book, $user) {
            $book = CredentialBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->registry->integrity($book), 409, 'A cadeia de integridade do livro divergiu.');
            $book->load('school', 'registrations.document', 'registrations.registrar.person', 'registrations.files', 'registrations.events');
            $head = $book->events()->latest('id')->value('event_hash');
            $bytes = Pdf::loadView('reports.credential-book', compact('book', 'head'))->setPaper('a4')->output();
            $file = $this->registry->storeFile($book, null, $user, 'book_export', $bytes, ['head_hash' => $head, 'records' => $book->registrations->count()]);
            $this->registry->event($book, null, $user, 'book_export', ['file_id' => $file->id, 'sha256' => $file->sha256, 'head_hash' => $head]);

            return $file;
        });
    }

    /** Cópia com metadados, eventos e todos os arquivos necessários à recuperação. */
    public function backup(CredentialBook $book, ?User $user)
    {
        return DB::transaction(function () use ($book, $user) {
            $book = CredentialBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->registry->integrity($book), 409, 'A cadeia de integridade do livro divergiu.');
            $book->load('school', 'registrations.document', 'events', 'files');
            $path = tempnam(sys_get_temp_dir(), 'sge-registry-');
            $zip = new \ZipArchive;
            $opened = false;
            try {
                if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Não foi possível preparar a cópia.');
                }
                $opened = true;
                $hashes = [];
                foreach ($book->registrations as $entry) {
                    $name = 'originais/'.$entry->number.'.pdf';
                    $bytes = $this->registry->bytes($entry->document->file_path, $entry->document_sha256);
                    $zip->addFromString($name, $bytes);
                    $hashes[$name] = $entry->document_sha256;
                }
                foreach ($book->files->where('kind', '!=', 'backup') as $file) {
                    $name = 'arquivos/'.$file->id.'-'.$file->kind.'.pdf';
                    $zip->addFromString($name, $this->registry->bytes($file->file_path, $file->sha256));
                    $hashes[$name] = $file->sha256;
                }
                $metadata = json_encode($book->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $zip->addFromString('livro.json', $metadata);
                $hashes['livro.json'] = hash('sha256', $metadata);
                $zip->addFromString('integridade.json', json_encode($hashes, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
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
            $file = $this->registry->storeFile($book, null, $user, 'backup', $bytes, ['records' => $book->registrations->count()]);
            $this->registry->event($book, null, $user, 'backup', ['file_id' => $file->id, 'sha256' => $file->sha256]);

            return $file;
        });
    }
}
