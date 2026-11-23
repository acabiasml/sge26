<?php

namespace App\Support;

use App\Models\CredentialBook;
use App\Models\CredentialRegistration;
use App\Models\CredentialRegistryEvent;
use App\Models\CredentialRegistryFile;
use App\Models\IssuedDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CredentialRegistry
{
    public const TYPES = ['technical-intermediate-certificate' => 'certificate', 'free-course-certificate' => 'certificate', 'technical-course-diploma' => 'diploma'];

    public const ACTIONS = ['opened' => 'Abertura', 'registered' => 'Registro', 'closed' => 'Encerramento', 'annotation' => 'Averbação', 'cancelled' => 'Cancelamento', 'signed_document' => 'PDF assinado e conferência externa', 'delivered' => 'Protocolo de entrega', 'book_export' => 'Consolidação do livro', 'book_signed' => 'Livro assinado e conferência externa', 'backup' => 'Cópia de segurança'];

    public function authorize(User $user, int $schoolId): void
    {
        abort_unless($user->canManagePeople() && $user->canManageSchool($schoolId), 403);
    }

    public function books(User $user, ?int $schoolId = null, ?string $kind = null)
    {
        return CredentialBook::with('school')->when(! $user->isAdministrator(), fn ($q) => $q->whereIn('school_id', $user->visibleSchoolIds()))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))->when($kind, fn ($q) => $q->where('kind', $kind));
    }

    public function requiresBook(int $schoolId, string $kind): bool
    {
        return CredentialBook::where('school_id', $schoolId)->where('kind', $kind)->exists();
    }

    public function open(User $user, array $data): CredentialBook
    {
        $this->authorize($user, $data['school_id']);

        return DB::transaction(function () use ($user, $data) {
            School::whereKey($data['school_id'])->lockForUpdate()->firstOrFail();
            $this->ensure(! CredentialBook::where('school_id', $data['school_id'])->where('kind', $data['kind'])
                ->where('identifier', $data['identifier'])->exists(), 'Este livro já existe nesta escola para esse tipo de documento.');
            $book = CredentialBook::create($data + ['next_number' => $data['first_number'], 'created_by_user_id' => $user->id]);
            $this->event($book, null, $user, 'opened', ['term' => $book->opening_term, 'previous_book' => $book->previous_book, 'first_number' => $book->first_number, 'first_folio' => $book->first_folio, 'identity_sha256' => $this->digest($book->only(['school_id', 'kind', 'identifier', 'reference_year', 'first_number', 'first_folio', 'opened_on', 'opening_term', 'previous_book']))]);

            return $book;
        });
    }

    /** O número e a folha só são consumidos se PDF e registro forem gravados juntos. */
    public function issue(int $bookId, int $schoolId, string $type, User $user, string $date, ?string $sistec, callable $emit, ?string $requestToken = null): IssuedDocument
    {
        $this->authorize($user, $schoolId);

        return DB::transaction(function () use ($bookId, $schoolId, $type, $user, $date, $sistec, $emit, $requestToken) {
            $book = CredentialBook::whereKey($bookId)->lockForUpdate()->firstOrFail();
            $this->ensure($book->school_id === $schoolId && $book->kind === (self::TYPES[$type] ?? null), 'Selecione um livro da escola e do tipo correto.');
            $key = $requestToken ? hash('sha256', $book->id.'|'.$user->id.'|'.$requestToken) : null;
            if ($key && ($existing = CredentialRegistration::where('issuance_key', $key)->first())) {
                return $existing->document;
            }
            $this->ensure(! $book->closed_at, 'O livro está encerrado. Abra outro livro para novos registros.');
            $this->ensure($date >= $book->opened_on->toDateString() && $date <= now('America/Cuiaba')->toDateString(), 'A data do registro deve estar entre a abertura do livro e hoje.');
            $this->ensure($type !== 'technical-course-diploma' || filled($sistec), 'Informe o número gerado pelo SISTEC para o diploma técnico definitivo.');
            $this->ensure($this->integrity($book), 'A cadeia de integridade do livro divergiu.');
            $number = $book->next_number;
            $folio = $book->first_folio + $number - $book->first_number;
            $this->ensure($number <= 999999999 && $folio <= 999999999, 'O limite da numeração foi atingido. Encerre o livro e abra outro.');
            $registration = ['registry_number' => (string) $number, 'registry_folio' => (string) $folio, 'registry_book' => $book->identifier,
                'registered_by' => $user->person?->full_name ?: $user->name, 'registration_date' => $date, 'sistec_code' => $sistec];
            $document = $emit($registration);
            $this->ensure($document instanceof IssuedDocument && $document->exists && $document->school_id === $schoolId
                && $document->type === $type && ! $document->revoked_at, 'O documento não corresponde ao registro solicitado.');
            $this->ensure($document->file_path && $document->file_sha256 && hash_equals($document->file_sha256, hash('sha256', Storage::disk('local')->get($document->file_path))), 'O PDF original precisa estar arquivado antes do registro.');
            $entry = CredentialRegistration::create(['credential_book_id' => $book->id, 'issued_document_id' => $document->id,
                'issuance_key' => $key, 'number' => $number, 'folio' => $folio, 'registered_on' => $date, 'sistec_code' => $sistec,
                'snapshot' => $document->payload, 'document_sha256' => $document->file_sha256, 'registered_by_user_id' => $user->id, 'created_at' => now()]);
            $book->update(['next_number' => $number + 1]);
            $this->event($book, $entry, $user, 'registered', ['number' => $number, 'folio' => $folio, 'document_sha256' => $entry->document_sha256, 'registered_on' => $date, 'sistec_code' => $sistec, 'snapshot_sha256' => $this->digest($entry->snapshot)]);

            return $document;
        });
    }

    public function event(CredentialBook $book, ?CredentialRegistration $entry, ?User $user, string $action, array $data): CredentialRegistryEvent
    {
        // Todos os chamadores mantêm o bloqueio do livro até concluir a transação.
        $fileIds = collect($data)->filter(fn ($value, $key) => is_int($value) && ($key === 'file_id' || str_ends_with($key, '_file_id') || $key === 'validation_report_id'))->values();
        if ($fileIds->isNotEmpty()) {
            $data['file_digests'] = $book->files()->whereIn('id', $fileIds)->get()->mapWithKeys(fn ($file) => [$file->id => $this->digest($file->toArray())])->all();
        }
        $previous = $book->events()->latest('id')->value('event_hash') ?? str_repeat('0', 64);
        $values = ['credential_book_id' => $book->id, 'credential_registration_id' => $entry?->id, 'actor_user_id' => $user?->id,
            'action' => $action, 'data' => $data, 'previous_hash' => $previous, 'occurred_at' => now()->format('Y-m-d H:i:s.u')];

        return CredentialRegistryEvent::create($values + ['event_hash' => $this->digest($values)]);
    }

    public function integrity(CredentialBook $book): bool
    {
        $opening = $book->events()->where('action', 'opened')->first();
        if (! $opening || ($opening->data['identity_sha256'] ?? '') !== $this->digest($book->only(['school_id', 'kind', 'identifier', 'reference_year', 'first_number', 'first_folio', 'opened_on', 'opening_term', 'previous_book']))) {
            return false;
        }
        if ($book->next_number !== $book->first_number + $book->registrations()->count()) {
            return false;
        }
        $closing = $book->events()->where('action', 'closed')->latest('id')->first();
        if ((bool) $book->closed_at !== (bool) $closing || ($closing && ($closing->data['term'] ?? null) !== $book->closing_term)) {
            return false;
        }
        $previous = str_repeat('0', 64);
        foreach ($book->events()->orderBy('id')->cursor() as $event) {
            $values = $event->only(['credential_book_id', 'credential_registration_id', 'actor_user_id', 'action', 'data', 'previous_hash']);
            $values['occurred_at'] = $event->occurred_at->format('Y-m-d H:i:s.u');
            if ($previous !== $event->previous_hash || ! hash_equals($event->event_hash, $this->digest($values))) {
                return false;
            }
            foreach ($event->data['file_digests'] ?? [] as $fileId => $digest) {
                $file = $book->files()->find($fileId);
                if (! $file || ! hash_equals($digest, $this->digest($file->toArray()))) {
                    return false;
                }
            }
            $previous = $event->event_hash;
        }
        foreach ($book->registrations()->with(['document', 'events'])->get() as $entry) {
            $record = $entry->events->firstWhere('action', 'registered');
            if (! $record || ($record->data['number'] ?? null) !== $entry->number || ($record->data['folio'] ?? null) !== $entry->folio || ($record->data['snapshot_sha256'] ?? '') !== $this->digest($entry->snapshot)
                || ($record->data['registered_on'] ?? null) !== $entry->registered_on->toDateString()
                || ($record->data['sistec_code'] ?? null) !== $entry->sistec_code
                || ($record->data['document_sha256'] ?? null) !== $entry->document_sha256
                || (bool) $entry->document?->revoked_at !== $entry->events->contains('action', 'cancelled')
                || $entry->document_sha256 !== $entry->document?->file_sha256 || $this->digest($entry->snapshot) !== $this->digest($entry->document->payload)) {
                return false;
            }
        }

        return true;
    }

    public function digest(array $values): string
    {
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return hash('sha256', json_encode($sort($values), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function storeFile(CredentialBook $book, ?CredentialRegistration $entry, ?User $user, string $kind, string $bytes, array $metadata = []): CredentialRegistryFile
    {
        $path = 'credential-registry/'.$book->id.'/'.Str::uuid().'.'.($kind === 'backup' ? 'zip' : 'pdf');
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Não foi possível arquivar o arquivo.');
        }
        chmod(Storage::disk('local')->path($path), 0600);
        try {
            return CredentialRegistryFile::create(['credential_book_id' => $book->id, 'credential_registration_id' => $entry?->id,
                'uploaded_by_user_id' => $user?->id, 'kind' => $kind, 'file_path' => $path, 'sha256' => hash('sha256', $bytes), 'metadata' => $metadata, 'created_at' => now()]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function bytes(string $path, string $hash): string
    {
        abort_unless(Storage::disk('local')->exists($path), 404, 'O arquivo arquivado não está disponível.');
        $bytes = Storage::disk('local')->get($path);
        abort_unless(hash_equals($hash, hash('sha256', $bytes)), 409, 'O arquivo diverge da cópia arquivada.');

        return $bytes;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['registry_book_id' => __($message)]);
        }
    }
}
