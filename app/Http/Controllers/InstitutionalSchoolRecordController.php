<?php

namespace App\Http\Controllers;

use App\Models\CredentialRegistryEvent;
use App\Models\IssuedDocument;
use App\Models\School;
use App\Support\CredentialRegistry;
use App\Support\InstitutionalSchoolRecord;
use App\Support\ProxyDelivery;
use App\Support\SchoolRecordPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstitutionalSchoolRecordController extends Controller
{
    public function index(Request $request, CredentialRegistry $registry)
    {
        $user = $request->user();
        abort_unless($user->canManagePeople(), 403);
        $definitions = InstitutionalSchoolRecord::definitions();
        $schools = School::query()->when(! $user->isAdministrator(), fn ($query) => $query->whereIn('id', $user->manageableSchoolIds()))
            ->orderBy('name')->get();
        $data = $request->validate([
            'school' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::in(array_keys($definitions))],
            'delivery_event' => ['nullable', 'integer', 'min:1'],
            'book' => ['nullable', 'integer'],
        ]);
        $school = isset($data['school']) ? $schools->find($data['school']) : $schools->first();
        abort_if(isset($data['school']) && ! $school, 403);
        $kind = $data['kind'] ?? 'visit';
        $definition = $definitions[$kind];
        $event = $school && $kind === 'delivery'
            ? $this->deliveryEvent($school, $data['delivery_event'] ?? null)
            : null;
        if ($event && ($entry = $this->deliveryRegistration($event))) {
            return redirect()->route('credential-registry.entry', $entry->registration);
        }
        $defaults = $event ? $this->deliveryFields($event) : [];
        $books = $school ? $registry->digitalBooks($user, $school, $kind)->get() : collect();
        $preferredBookId = $data['book'] ?? null;

        return view('credential-registry.institutional-record', compact(
            'schools', 'school', 'definitions', 'kind', 'definition', 'event', 'defaults', 'books', 'preferredBookId',
        ));
    }

    public function store(Request $request, School $school, string $kind, CredentialRegistry $registry, SchoolRecordPdf $records)
    {
        $registry->authorize($request->user(), $school->id);
        $definition = InstitutionalSchoolRecord::definitions()[$kind] ?? abort(404);
        $eventId = $request->validate(['delivery_event' => ['nullable', 'integer', 'min:1']])['delivery_event'] ?? null;
        abort_if($eventId && $kind !== 'delivery', 404);
        $event = $this->deliveryEvent($school, $eventId);
        $fields = $definition['fields'];
        $file = ['file', 'mimes:pdf', 'max:20480'];
        $today = now('America/Cuiaba')->toDateString();
        $rules = SchoolRecordPdf::registryRules($school) + [
            'registration_date' => $school->usesPhysicalRegistry() ? ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today] : ['exclude'],
            'reviewed' => ['accepted'],
        ];
        if (! $event) {
            $rules += [
                'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
                'original_pdf' => [$definition['required'] ? 'required' : 'nullable', ...$file],
            ];
            foreach ($fields as $name => [, $input]) {
                $rules[$name] = is_array($input)
                    ? ['required', Rule::in(array_keys($input))]
                    : ['required', 'string', 'max:'.($input === 'textarea' ? 6000 : 250)];
            }
            if ($kind === 'delivery') {
                $rules['authority_pdf'] = ['required_unless:recipient_kind,student', 'nullable', ...$file];
                $rules += ProxyDelivery::rules('recorded_on');
            }
        }
        $data = $request->validate($rules);
        if ($event) {
            $data += $this->deliveryFields($event);
        } elseif ($kind === 'delivery') {
            ProxyDelivery::validateTerm($data);
        }
        $actDate = $data['recorded_on'];
        if (isset($data['registration_date']) && $data['registration_date'] < $actDate) {
            throw ValidationException::withMessages(['registration_date' => 'A data do registro em papel não pode anteceder o ato.']);
        }
        $evidence = [];
        if ($event) {
            abort_unless(isset($event->data['receipt_file_id']), 409, 'O protocolo assinado não foi localizado.');
            foreach (['receipt_file_id' => 'signed_receipt', 'authority_file_id' => 'representative_authority'] as $key => $label) {
                if (! empty($event->data[$key])) {
                    $source = $event->registration->files()->findOrFail($event->data[$key]);
                    $evidence[$label] = $registry->bytes($source->file_path, $source->sha256);
                }
            }
        } elseif ($request->hasFile('original_pdf')) {
            $evidence[$kind === 'delivery' ? 'signed_receipt' : 'institutional_original'] = $request->file('original_pdf')->get();
        }
        if (isset($data['authority_pdf'])) {
            $evidence['representative_authority'] = $data['authority_pdf']->get();
        }

        $type = array_search($kind, InstitutionalSchoolRecord::DOCUMENT_TYPES, true);
        $payload = [
            'title' => InstitutionalSchoolRecord::title($kind),
            'registry_subject' => $data['subject'],
            'act_date' => $actDate,
            'institutional_fields' => InstitutionalSchoolRecord::displayFields($fields, $data),
            'original_received' => true,
        ];
        if ($kind === 'delivery') {
            foreach (ProxyDelivery::DATE_LABELS as $field => $label) {
                if (isset($data[$field])) {
                    $payload[$field] = $data[$field];
                    $payload['institutional_fields'][$label] = \Carbon\CarbonImmutable::parse($data[$field])->format('d/m/Y');
                }
            }
        }
        if ($event) {
            $payload['delivery_event_id'] = $event->id;
            $payload['source_registration_id'] = $event->credential_registration_id;
        }
        $document = DB::transaction(function () use ($event, $school, $records, $request, $type, $payload, $data, $evidence) {
            // The same protocol may be opened in two tabs or in two different books.
            School::whereKey($school->id)->lockForUpdate()->firstOrFail();
            if ($event && ($existing = $this->deliveryRegistration($event))) {
                return $existing;
            }

            return $records->issue(
                $request->user(), $school, $type, null, $payload, $data,
                fn (IssuedDocument $document) => InstitutionalSchoolRecord::pdf($school, $document),
                evidence: $evidence,
            );
        });

        return redirect()->route('credential-registry.entry', $document->registration)
            ->with('status', __('Ato registrado no livro. Os comprovantes foram arquivados.'));
    }

    public function pendingDeliveries(Request $request, School $school)
    {
        app(CredentialRegistry::class)->authorize($request->user(), $school->id);
        $pending = $this->deliveryEvents($school)->whereNotExists(function ($query) use ($school) {
            $query->selectRaw('1')->from('issued_documents')
                ->where('issued_documents.school_id', $school->id)
                ->where('issued_documents.type', 'credential-delivery')
                ->whereColumn('issued_documents.payload->delivery_event_id', 'credential_registry_events.id');
        })->latest('id')->paginate(10);
        $pending->through(fn (CredentialRegistryEvent $event) => [
            'id' => $event->id,
            'details' => $this->deliveryFields($event),
        ]);

        return view('livewire.listings.institutional-pending-deliveries', compact('pending', 'school'));
    }

    private function deliveryEvent(School $school, ?int $id): ?CredentialRegistryEvent
    {
        if (! $id) {
            return null;
        }

        return $this->deliveryEvents($school)->findOrFail($id);
    }

    private function deliveryEvents(School $school)
    {
        return CredentialRegistryEvent::query()->where('action', 'delivered')
            ->whereHas('book', fn ($query) => $query->where('school_id', $school->id)
                ->whereIn('kind', InstitutionalSchoolRecord::CERTIFICATE_BOOKS))
            ->whereHas('registration', fn ($query) => $query->whereColumn('credential_book_id', 'credential_registry_events.credential_book_id'))
            ->with('registration.document.person', 'book');
    }

    private function deliveryRegistration(CredentialRegistryEvent $event): ?IssuedDocument
    {
        return IssuedDocument::query()->where('school_id', $event->book->school_id)
            ->where('type', 'credential-delivery')->where('payload->delivery_event_id', $event->id)
            ->whereHas('registration')->with('registration')->first();
    }

    private function deliveryFields(CredentialRegistryEvent $event): array
    {
        $registration = $event->registration;
        $snapshot = $registration->snapshot;
        $holder = $snapshot['student']['full_name'] ?? $snapshot['participant_name'] ?? $registration->document->person?->full_name;

        return [
            'subject' => implode(' · ', array_filter([
                $snapshot['title'], $holder, 'Livro '.$event->book->identifier,
                'Folha '.$registration->referenceFolio(), 'Registro '.$registration->referenceNumber(),
            ])),
            'recipient' => $event->data['recipient'],
            'recipient_kind' => $event->data['recipient_kind'],
            'method' => $event->data['method'],
            'recorded_on' => $event->data['delivered_on'],
        ] + array_intersect_key($event->data, ProxyDelivery::DATE_LABELS);
    }
}
