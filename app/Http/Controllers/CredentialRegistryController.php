<?php

namespace App\Http\Controllers;

use App\Models\CredentialBook;
use App\Models\CredentialRegistration;
use App\Models\CredentialRegistryFile;
use App\Models\School;
use App\Support\CredentialBookArchive;
use App\Support\CredentialRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CredentialRegistryController extends Controller
{
    public function __construct(private CredentialRegistry $registry) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $schools = School::when(! $request->user()->isAdministrator(), fn ($q) => $q->whereIn('id', $request->user()->visibleSchoolIds()))->orderBy('name')->get();
        $books = $this->registry->books($request->user())->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('reference_year', $request->integer('year')))->withCount('registrations')->orderBy('identifier')->paginate(10)->withQueryString();

        return view('credential-registry.index', compact('schools', 'books'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['school_id' => ['required', 'integer', 'exists:schools,id'], 'kind' => ['required', Rule::in(array_keys(CredentialBook::KINDS))],
            'identifier' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN.\/-]+$/u'], 'reference_year' => ['required', 'integer', 'between:1900,2100'],
            'first_number' => ['required', 'integer', 'between:1,999999999'], 'first_folio' => ['required', 'integer', 'between:1,999999999'],
            'opened_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'opening_term' => ['required', 'string', 'max:10000'], 'previous_book' => ['nullable', 'string', 'max:255']]);
        $this->registry->authorize($request->user(), $data['school_id']);
        $data['identifier'] = mb_strtoupper(trim($data['identifier']));
        if (CredentialBook::where('school_id', $data['school_id'])->where('kind', $data['kind'])->where('identifier', $data['identifier'])->exists()) {
            throw ValidationException::withMessages(['identifier' => 'Este livro já existe nesta escola e tipo.']);
        }

        return redirect()->route('credential-registry.show', $this->registry->open($request->user(), $data))->with('status', 'Livro aberto. Confira as prévias antes de efetuar registros definitivos.');
    }

    public function show(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);
        $book->load('school');
        $entries = $book->registrations()->with('document', 'files', 'events')->when($request->filled('q'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('number', $request->input('q'))->orWhereHas('document.person', fn ($p) => $p->where('full_name', 'like', '%'.$request->input('q').'%'));
            });
        })->orderBy('number')->paginate(10)->withQueryString();
        $files = $book->files()->whereNull('credential_registration_id')->latest('id')->paginate(10, ['*'], 'files_page');
        $events = $book->events()->with('actor.person')->latest('id')->paginate(10, ['*'], 'events_page');
        $integrity = $this->registry->integrity($book);

        return view('credential-registry.show', compact('book', 'entries', 'files', 'events', 'integrity'));
    }

    public function close(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);
        $data = $request->validate(['closing_term' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($book, $request, $data) {
            $book = CredentialBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            abort_if($book->closed_at, 409, 'Livro já encerrado.');
            $book->update($data + ['closed_at' => now()]);
            $this->registry->event($book, null, $request->user(), 'closed', ['term' => $data['closing_term'], 'last_number' => $book->next_number - 1]);
        });

        return back()->with('status', 'Livro encerrado. Os registros e arquivos permanecem disponíveis.');
    }

    public function entry(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        $entry->load('document', 'registrar.person', 'files', 'events.actor.person');
        $signers = $this->signers($entry);
        $files = $entry->files()->latest('id')->paginate(10, ['*'], 'files_page');
        $events = $entry->events()->with('actor.person')->latest('id')->paginate(10, ['*'], 'events_page');

        return view('credential-registry.entry', compact('entry', 'signers', 'files', 'events'));
    }

    public function annotate(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        $data = $request->validate(['text' => ['required', 'string', 'max:5000']]);

        return $this->change($request, $entry, function ($book, $entry) use ($data, $request) {
            $this->registry->event($book, $entry, $request->user(), 'annotation', $data);
        }, 'Averbação acrescentada. O original foi preservado.');
    }

    public function cancel(Request $request, CredentialRegistration $entry)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);

        return $this->change($request, $entry, function ($book, $entry) use ($data, $request) {
            abort_if($entry->document->revoked_at, 409, 'Registro já cancelado.');
            $entry->document->update(['revoked_at' => now()]);
            $this->registry->event($book, $entry, $request->user(), 'cancelled', $data);
        }, 'Registro cancelado. Número, arquivo original e trilha de auditoria foram preservados.');
    }

    public function signed(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        $signers = $this->signers($entry);
        $data = $request->validate(['signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'], 'validation_report' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'signers' => ['required', 'array', 'min:1'], 'signers.*' => ['required', Rule::in(array_keys($signers)), 'distinct'],
            'reviewed' => ['accepted'], 'timestamp_checked' => ['nullable', 'boolean'], 'validator' => ['required', 'string', 'max:150']]);

        return $this->change($request, $entry, function ($book, $entry) use ($request, $data, $signers) {
            abort_if($entry->document->revoked_at, 409, 'Registro cancelado.');
            $previous = $entry->files()->where('kind', 'signed_document')->latest('id')->first();
            if (array_diff($previous?->metadata['signers'] ?? [], $data['signers'])) {
                throw ValidationException::withMessages(['signers' => 'A nova versão precisa conservar os signatários já conferidos.']);
            }
            $metadata = ['signers' => $data['signers'], 'required_signers' => $signers, 'complete' => count($data['signers']) === count($signers),
                'timestamp_checked' => $request->boolean('timestamp_checked'), 'validator' => $data['validator'], 'review_type' => 'external_human_review',
                'original_sha256' => $entry->document_sha256];
            $signedPdf = $request->file('signed_pdf')->get();
            $validationPdf = $request->file('validation_report')->get();
            $this->registry->bytes($entry->document->file_path, $entry->document_sha256);
            if (hash_equals($entry->document_sha256, hash('sha256', $signedPdf)) || $signedPdf === $validationPdf) {
                throw ValidationException::withMessages(['signed_pdf' => 'Anexe o PDF assinado e o relatório de validação em arquivos distintos.']);
            }
            $report = $this->trackedFile($book, $entry, $request, 'validation_report', $validationPdf, $metadata);
            $file = $this->trackedFile($book, $entry, $request, 'signed_document', $signedPdf, $metadata + ['validation_report_id' => $report->id]);
            $this->registry->event($book, $entry, $request->user(), 'signed_document', $metadata + ['file_id' => $file->id, 'sha256' => $file->sha256, 'validation_report_id' => $report->id]);
        }, 'PDF arquivado com conferência externa registrada. As versões anteriores foram preservadas.');
    }

    public function deliver(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        $data = $request->validate(['method' => ['required', Rule::in(['physical', 'digital'])], 'recipient' => ['required', 'string', 'max:150'],
            'recipient_kind' => ['required', Rule::in(['student', 'guardian', 'proxy'])], 'delivered_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$entry->registered_on->toDateString()],
            'receipt' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'authority' => ['required_unless:recipient_kind,student', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'authority_issued_on' => ['required_if:recipient_kind,proxy', 'nullable', 'date_format:Y-m-d', 'before_or_equal:delivered_on'], 'reviewed' => ['accepted']]);

        if ($data['recipient_kind'] === 'proxy'
            && $data['authority_issued_on'] < \Carbon\CarbonImmutable::parse($data['delivered_on'])->subMonthsNoOverflow(6)->toDateString()) {
            throw ValidationException::withMessages(['authority_issued_on' => 'A procuração deve ter sido emitida nos seis meses anteriores à entrega.']);
        }

        return $this->change($request, $entry, function ($book, $entry) use ($request, $data) {
            abort_if($entry->document->revoked_at, 409, 'Registro cancelado.');
            $signed = $entry->files()->where('kind', 'signed_document')->latest('id')->first();
            if ($data['method'] === 'digital' && (! $signed || ! ($signed->metadata['complete'] ?? false) || ! ($signed->metadata['timestamp_checked'] ?? false))) {
                throw ValidationException::withMessages(['method' => 'A expedição digital exige todas as assinaturas e o carimbo de tempo conferidos externamente, com relatório arquivado.']);
            }
            if ($data['method'] === 'digital') {
                $report = $entry->files()->where('kind', 'validation_report')->findOrFail($signed->metadata['validation_report_id'] ?? null);
                $this->registry->bytes($signed->file_path, $signed->sha256);
                $this->registry->bytes($report->file_path, $report->sha256);
            }
            $metadata = collect($data)->except(['receipt', 'authority', 'reviewed'])->all();
            $receipt = $this->trackedFile($book, $entry, $request, 'receipt', $request->file('receipt')->get(), $metadata);
            if ($request->hasFile('authority')) {
                $authority = $this->trackedFile($book, $entry, $request, 'authority', $request->file('authority')->get(), $metadata);
                $metadata['authority_file_id'] = $authority->id;
            }
            $this->registry->event($book, $entry, $request->user(), 'delivered', $metadata + ['receipt_file_id' => $receipt->id, 'signed_file_id' => $signed?->id]);
        }, 'Protocolo de entrega arquivado.');
    }

    public function file(Request $request, CredentialRegistryFile $file)
    {
        $this->registry->authorize($request->user(), $file->book->school_id);
        $bytes = $this->registry->bytes($file->file_path, $file->sha256);

        return response($bytes, 200, ['Content-Type' => $file->kind === 'backup' ? 'application/zip' : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="beaba-acervo-'.$file->id.'.'.($file->kind === 'backup' ? 'zip' : 'pdf').'"', 'Cache-Control' => 'private, no-store']);
    }

    public function original(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);

        return response($this->registry->bytes($entry->document->file_path, $entry->document_sha256), 200, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function export(Request $request, CredentialBook $book, CredentialBookArchive $archive)
    {
        $this->registry->authorize($request->user(), $book->school_id);
        $file = $archive->pdf($book, $request->user());

        return redirect()->route('credential-registry.files', $file);
    }

    public function backup(Request $request, CredentialBook $book, CredentialBookArchive $archive)
    {
        $this->registry->authorize($request->user(), $book->school_id);

        return redirect()->route('credential-registry.files', $archive->backup($book, $request->user()));
    }

    public function signBook(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);
        $data = $request->validate(['source_file_id' => ['required', 'integer'], 'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'], 'validation_report' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'reviewed' => ['accepted']]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $book, $data, &$paths) {
                $book = CredentialBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
                $source = $book->files()->where('kind', 'book_export')->findOrFail($data['source_file_id']);
                abort_unless($this->registry->integrity($book), 409, 'Divergência na cadeia de integridade do livro.');
                $signed = $request->file('signed_pdf')->get();
                $report = $request->file('validation_report')->get();
                if (hash_equals($source->sha256, hash('sha256', $signed)) || $signed === $report) {
                    throw ValidationException::withMessages(['signed_pdf' => 'Os arquivos do livro assinado e da validação devem ser distintos do original.']);
                }
                $fileIds = [];
                foreach (['signed_pdf' => 'book_signed', 'validation_report' => 'book_validation'] as $input => $kind) {
                    $file = $this->registry->storeFile($book, null, $request->user(), $kind, $request->file($input)->get(), ['source_file_id' => $source->id, 'source_sha256' => $source->sha256, 'review_type' => 'external_human_review']);
                    $paths[] = $file->file_path;
                    $fileIds[$kind] = $file->id;
                }
                $this->registry->event($book, null, $request->user(), 'book_signed', [
                    'source_file_id' => $source->id, 'source_sha256' => $source->sha256,
                    'file_id' => $fileIds['book_signed'], 'validation_report_id' => $fileIds['book_validation'],
                ]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($paths);
            throw $error;
        }

        return back()->with('status', 'Versão assinada do livro e relatório de validação externa arquivados.');
    }

    public function signers(CredentialRegistration $entry): array
    {
        $registration = $entry->snapshot['registration'] ?? [];
        $roles = ['director' => $registration['director_name'] ?? 'Direção', 'secretary' => $registration['secretary_name'] ?? ($registration['instructor_name'] ?? 'Secretaria')];
        if (filled($registration['registered_by'] ?? null) && ! collect($roles)->contains(fn ($name) => mb_strtolower(trim($name)) === mb_strtolower(trim($registration['registered_by'])))) {
            $roles['registrar'] = $registration['registered_by'];
        }

        return $roles;
    }

    private array $pendingPaths = [];

    private function trackedFile($book, $entry, $request, $kind, $bytes, $metadata)
    {
        $file = $this->registry->storeFile($book, $entry, $request->user(), $kind, $bytes, $metadata);
        $this->pendingPaths[] = $file->file_path;

        return $file;
    }

    private function change(Request $request, CredentialRegistration $entry, callable $callback, string $message)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        $this->pendingPaths = [];
        try {
            DB::transaction(function () use ($entry, $callback) {
                $book = CredentialBook::whereKey($entry->credential_book_id)->lockForUpdate()->firstOrFail();
                abort_unless($this->registry->integrity($book), 409, 'Divergência na cadeia de integridade do livro.');
                $callback($book, $entry->fresh(['document', 'book']));
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($this->pendingPaths);
            throw $error;
        }

        return back()->with('status', $message);
    }
}
