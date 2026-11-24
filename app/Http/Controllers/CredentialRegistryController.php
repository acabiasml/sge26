<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\CredentialBook;
use App\Models\CredentialRegistration;
use App\Models\CredentialRegistryFile;
use App\Models\School;
use App\Support\CredentialBookArchive;
use App\Support\CredentialRegistry;
use App\Support\InstitutionalSchoolRecord;
use App\Support\PdfSignatureEvidence;
use Carbon\CarbonImmutable;
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
            'scope' => ['exclude_unless:kind,high-school', 'required', Rule::in(array_keys(AcademicCourse::MODALITY_LABELS))],
            'first_number' => ['required', 'integer', 'between:1,999999999'], 'first_folio' => ['required', 'integer', 'between:1,999999999'],
            'opened_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'opening_term' => ['required', 'string', 'max:10000'], 'previous_book' => ['nullable', 'string', 'max:255']]);
        $this->registry->authorize($request->user(), $data['school_id']);
        $data['identifier'] = mb_strtoupper(trim($data['identifier']));
        if (CredentialBook::where('school_id', $data['school_id'])->where('kind', $data['kind'])->where('medium', 'digital')->where('identifier', $data['identifier'])->exists()) {
            throw ValidationException::withMessages(['identifier' => 'Este livro já existe nesta escola e tipo.']);
        }

        return redirect()->route('credential-registry.show', $this->registry->open($request->user(), $data))->with('status', 'Livro aberto. Confira as prévias antes de efetuar registros definitivos.');
    }

    public function show(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);
        $book->load('school');
        $entries = $this->registrations($request, $book);
        $files = $book->files()->whereNull('credential_registration_id')->latest('id')->paginate(10, ['*'], 'files_page');
        $events = $this->operationEvents($book);
        $integrity = $this->registry->integrity($book);

        return view('credential-registry.show', compact('book', 'entries', 'files', 'events', 'integrity'));
    }

    public function bookEntries(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);

        return view('credential-registry.show', ['entries' => $this->registrations($request, $book)]);
    }

    public function bookEvents(Request $request, CredentialBook $book)
    {
        $this->registry->authorize($request->user(), $book->school_id);

        return view('credential-registry.events', ['events' => $this->operationEvents($book)]);
    }

    public function entryEvents(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);

        return view('credential-registry.events', ['events' => $this->operationEvents($entry)]);
    }

    private function registrations(Request $request, CredentialBook $book)
    {
        $search = $request->query('q');

        return $book->registrations()->with('document', 'files')->when(is_string($search) && trim($search) !== '', function ($q) use ($book, $search) {
            $q->where(function ($query) use ($book, $search) {
                $query->where('snapshot->registration->registry_number', $search)
                    ->when(! $book->isPhysical() && ctype_digit($search), fn ($q) => $q->orWhere('number', (int) $search))
                    ->orWhere('snapshot->registry_subject', 'like', '%'.$search.'%')
                    ->orWhereHas('document.person', fn ($p) => $p->where('full_name', 'like', '%'.$search.'%'));
            });
        })->orderBy('number')->paginate(10)->withQueryString();
    }

    private function operationEvents(CredentialBook|CredentialRegistration $record)
    {
        return $record->events()->with('actor.person', 'book')->latest('id')->paginate(10, ['*'], 'events_page');
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
        $events = $this->operationEvents($entry);

        $physical = $entry->files()->where('kind', 'physical_signed')->latest('id')->first();
        $deliveryVersions = $entry->files->filter(fn ($file) => $file->kind === 'physical_signed'
            || ($file->kind === 'signed_document' && ($file->metadata['complete'] ?? false) && ($file->metadata['timestamp_checked'] ?? false)
                && ($file->metadata['content_signature_checked'] ?? false))
            || in_array($file->kind, ['second_copy', 'apostille'], true));

        return view('credential-registry.entry', compact('entry', 'signers', 'files', 'events', 'physical', 'deliveryVersions'));
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
        abort_if($entry->snapshot['original_received'] ?? false, 404);
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
            $original = $this->registry->bytes($entry->document->file_path, $entry->document_sha256);
            if (hash_equals($entry->document_sha256, hash('sha256', $signedPdf)) || $signedPdf === $validationPdf) {
                throw ValidationException::withMessages(['signed_pdf' => 'Anexe o PDF assinado e o relatório de validação em arquivos distintos.']);
            }
            app(PdfSignatureEvidence::class)->confirm($signedPdf, count($data['signers']), $original);
            $metadata['content_signature_checked'] = true;
            $report = $this->trackedFile($book, $entry, $request, 'validation_report', $validationPdf, $metadata);
            $file = $this->trackedFile($book, $entry, $request, 'signed_document', $signedPdf, $metadata + ['validation_report_id' => $report->id]);
            $this->registry->event($book, $entry, $request->user(), 'signed_document', $metadata + ['file_id' => $file->id, 'sha256' => $file->sha256, 'validation_report_id' => $report->id]);
        }, 'PDF arquivado com conferência externa registrada. As versões anteriores foram preservadas.');
    }

    public function physical(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        abort_if($entry->snapshot['original_received'] ?? false, 404);
        $data = $request->validate([
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'physical_location' => ['required', 'string', 'max:300'],
            'physical_reason' => [Rule::requiredIf($this->physicalExceptionApplies($entry)), 'nullable', Rule::in(['lost', 'damaged', 'name', 'gender', 'correction'])],
            'reviewed' => ['accepted'],
        ]);

        return $this->change($request, $entry, function ($book, $entry) use ($request, $data) {
            abort_if($entry->document->revoked_at, 409, 'Registro cancelado.');
            $bytes = $request->file('signed_pdf')->get();
            $this->registry->bytes($entry->document->file_path, $entry->document_sha256);
            if (hash_equals($entry->document_sha256, hash('sha256', $bytes))) {
                throw ValidationException::withMessages(['signed_pdf' => 'Anexe a cópia digitalizada com as assinaturas manuscritas.']);
            }
            $metadata = collect($data)->only(['physical_location', 'physical_reason'])->all() + [
                'review_type' => 'human_paper_review', 'original_sha256' => $entry->document_sha256,
            ];
            $file = $this->trackedFile($book, $entry, $request, 'physical_signed', $bytes, $metadata);
            $this->registry->event($book, $entry, $request->user(), 'physical_signed', $metadata + ['file_id' => $file->id]);
        }, 'Cópia assinada e localização do original físico arquivadas.');
    }

    public function amendment(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        abort_unless(in_array($entry->book->kind, ['certificate', 'diploma', 'high-school'], true), 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['second_copy', 'apostille'])],
            'reason' => ['required', 'string', 'max:2000'],
            'method' => ['required', Rule::in(['physical', 'digital'])],
            'signed_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'supporting_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'validation_report' => ['required_if:method,digital', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'timestamp_checked' => ['exclude_unless:method,digital', 'accepted'],
            'physical_reason' => ['required_if:method,physical', 'nullable', Rule::in(['lost', 'damaged', 'name', 'gender', 'correction'])],
            'reviewed' => ['accepted'],
        ]);

        return $this->change($request, $entry, function ($book, $entry) use ($request, $data) {
            abort_if($entry->document->revoked_at, 409, 'Registro cancelado.');
            $original = $this->registry->bytes($entry->document->file_path, $entry->document_sha256);
            $issued = $request->file('signed_pdf')->get();
            if ($original === $issued) {
                throw ValidationException::withMessages(['signed_pdf' => 'Anexe a via preparada para este pedido com a identificação do registro anterior.']);
            }
            $proof = $this->trackedFile($book, $entry, $request, 'amendment_proof', $request->file('supporting_pdf')->get(), []);
            $metadata = collect($data)->only(['reason', 'method', 'physical_reason'])->all() + [
                'supporting_file_id' => $proof->id, 'original_sha256' => $entry->document_sha256,
                'original_number' => $entry->referenceNumber(), 'original_book' => $book->identifier,
            ];
            if ($data['method'] === 'digital') {
                $reportBytes = $request->file('validation_report')->get();
                if ($issued === $reportBytes || $original === $reportBytes) {
                    throw ValidationException::withMessages(['validation_report' => 'Anexe o relatório de validação em um arquivo separado.']);
                }
                app(PdfSignatureEvidence::class)->confirm($issued, count($this->signers($entry)));
                $report = $this->trackedFile($book, $entry, $request, 'validation_report', $reportBytes, []);
                $metadata['validation_report_id'] = $report->id;
                $metadata['timestamp_checked'] = true;
            }
            $file = $this->trackedFile($book, $entry, $request, $data['action'], $issued, $metadata);
            $this->registry->event($book, $entry, $request->user(), $data['action'], $metadata + ['file_id' => $file->id]);
        }, 'Via arquivada no registro original.');
    }

    public function deliver(Request $request, CredentialRegistration $entry)
    {
        $this->registry->authorize($request->user(), $entry->book->school_id);
        abort_if(($entry->snapshot['original_received'] ?? false) || $entry->book->kind === 'final-results', 404);
        $data = $request->validate(['method' => ['required', Rule::in(['physical', 'digital'])], 'recipient' => ['required', 'string', 'max:150'],
            'recipient_kind' => ['required', Rule::in(['student', 'guardian', 'proxy'])], 'delivered_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$entry->registered_on->toDateString()],
            'delivery_token' => ['nullable', 'uuid'],
            'signed_file_id' => ['nullable', 'integer'], 'requested_on' => ['required_if:recipient_kind,proxy', 'nullable', 'date_format:Y-m-d', 'before_or_equal:delivered_on'],
            'receipt' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'authority' => ['required_unless:recipient_kind,student', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'authority_issued_on' => ['required_if:recipient_kind,proxy', 'nullable', 'date_format:Y-m-d', 'before_or_equal:requested_on'], 'reviewed' => ['accepted']]);

        if ($data['recipient_kind'] === 'proxy'
            && $data['authority_issued_on'] < CarbonImmutable::parse($data['requested_on'])->subMonthsNoOverflow(6)->toDateString()) {
            throw ValidationException::withMessages(['authority_issued_on' => 'A procuração deve ter sido emitida nos seis meses anteriores à solicitação.']);
        }
        $birthDate = $entry->document->person?->birth_date;
        if ($data['recipient_kind'] === 'guardian' && $birthDate
            && $birthDate->copy()->addYearsNoOverflow(18)->toDateString() <= $data['delivered_on']) {
            throw ValidationException::withMessages(['recipient_kind' => 'Para um titular maior de idade, utilize a entrega ao titular ou a um procurador.']);
        }

        $deliveryEvent = null;
        $response = $this->change($request, $entry, function ($book, $entry) use ($request, $data, &$deliveryEvent) {
            abort_if($entry->document->revoked_at, 409, 'Registro cancelado.');
            if (! empty($data['delivery_token']) && ($deliveryEvent = $entry->events()->where('action', 'delivered')
                ->where('data->delivery_token', $data['delivery_token'])->first())) {
                return;
            }
            $signed = $entry->files()->find($data['signed_file_id'] ?? 0);
            if (! $signed && empty($data['signed_file_id'])) {
                $signed = $entry->files()->where('kind', $data['method'] === 'digital' ? 'signed_document' : 'physical_signed')->latest('id')->first();
            }
            $amended = $signed && in_array($signed->kind, ['second_copy', 'apostille'], true);
            $ready = $signed && match ($data['method']) {
                'physical' => $signed->kind === 'physical_signed' || ($amended && $signed->metadata['method'] === 'physical'),
                'digital' => ($signed->kind === 'signed_document' && ($signed->metadata['complete'] ?? false) && ($signed->metadata['timestamp_checked'] ?? false)
                    && ($signed->metadata['content_signature_checked'] ?? false))
                    || ($amended && $signed->metadata['method'] === 'digital' && ($signed->metadata['timestamp_checked'] ?? false)),
            };
            if (! $ready) {
                throw ValidationException::withMessages(['method' => 'Selecione uma versão assinada e conferida compatível com a forma de entrega.']);
            }
            $this->registry->bytes($signed->file_path, $signed->sha256);
            if ($data['method'] === 'digital') {
                $report = $entry->files()->where('kind', 'validation_report')->findOrFail($signed->metadata['validation_report_id'] ?? null);
                $this->registry->bytes($report->file_path, $report->sha256);
            }
            $metadata = collect($data)->except(['receipt', 'authority', 'reviewed', 'signed_file_id'])->all();
            $receipt = $this->trackedFile($book, $entry, $request, 'receipt', $request->file('receipt')->get(), $metadata);
            if ($request->hasFile('authority')) {
                $authority = $this->trackedFile($book, $entry, $request, 'authority', $request->file('authority')->get(), $metadata);
                $metadata['authority_file_id'] = $authority->id;
            }
            $deliveryEvent = $this->registry->event($book, $entry, $request->user(), 'delivered', $metadata + ['receipt_file_id' => $receipt->id, 'signed_file_id' => $signed?->id]);
        }, 'Protocolo de entrega arquivado.');

        if (in_array($entry->book->kind, InstitutionalSchoolRecord::CERTIFICATE_BOOKS, true)) {
            return redirect()->route('institutional-records.index', [
                'school' => $entry->book->school_id, 'kind' => 'delivery', 'delivery_event' => $deliveryEvent->id,
            ])->with('status', __('Protocolo arquivado. Confira os dados e lance a entrega no livro próprio.'));
        }

        return $response;
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
                app(PdfSignatureEvidence::class)->confirm($signed, 1, $this->registry->bytes($source->file_path, $source->sha256));
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
        if (isset($entry->snapshot['signers'])) {
            return $entry->snapshot['signers'];
        }
        $registration = $entry->snapshot['registration'] ?? [];
        $roles = ['director' => $registration['director_name'] ?? 'Direção', 'secretary' => $registration['secretary_name'] ?? ($registration['instructor_name'] ?? 'Secretaria')];
        if (filled($registration['registered_by'] ?? null) && ! collect($roles)->contains(fn ($name) => mb_strtolower(trim($name)) === mb_strtolower(trim($registration['registered_by'])))) {
            $roles['registrar'] = $registration['registered_by'];
        }

        return $roles;
    }

    private function physicalExceptionApplies(CredentialRegistration $entry): bool
    {
        return in_array($entry->book->kind, ['certificate', 'diploma', 'high-school'], true);
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
