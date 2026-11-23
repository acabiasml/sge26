<?php

namespace App\Http\Controllers;

use App\Models\FreeCourseOffering;
use App\Models\IssuedDocument;
use App\Support\CredentialRegistry;
use App\Support\FreeCourseCertificate;
use App\Support\PdfLetterhead;
use App\Support\PdfMetadata;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FreeCourseCertificateController extends Controller
{
    private function authorizeOffering(Request $request, FreeCourseOffering $offering): void
    {
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($offering->freeCourse->school_id), 403);
    }

    public function prepare(Request $request, FreeCourseOffering $offering, FreeCourseCertificate $certificates)
    {
        $this->authorizeOffering($request, $offering);
        $offering->load('participants.person', 'freeCourse.school');
        $school = $offering->freeCourse->school;
        $logos = PdfLetterhead::make($school);

        return view('free-courses.prepare', ['offering' => $offering, 'certificates' => $certificates,
            'requiresBook' => app(CredentialRegistry::class)->requiresBook($school->id, 'certificate'),
            'books' => app(CredentialRegistry::class)->books($request->user(), $school->id, 'certificate')->whereNull('closed_at')->orderBy('identifier')->get(),
            'version' => $certificates->version($offering), 'logosReady' => $logos['maintainer_logo'] && $logos['school_logo'],
            'recent' => IssuedDocument::where('school_id', $school->id)->where('type', 'free-course-certificate')
                ->where('payload->offering_id', $offering->id)->latest('id')->limit(50)->get()]);
    }

    public function preview(Request $request, FreeCourseOffering $offering, int $participant, FreeCourseCertificate $certificates)
    {
        $this->authorizeOffering($request, $offering);
        $person = $offering->participants()->whereNull('removed_at')->with('person')->findOrFail($participant);
        $document = new IssuedDocument(['verification_code' => 'PREVIA-SEM-REGISTRO']);
        $document->payload = $this->payload($offering, $person, [
            'issued_date' => now('America/Cuiaba')->toDateString(), 'issued_place' => $offering->freeCourse->school->city,
            'registry_book' => '', 'registry_folio' => '', 'registry_number' => '', 'registered_by' => '',
            'director_name' => 'Nome completo a preencher', 'instructor_name' => 'Nome completo a preencher', 'instructor_role' => 'Responsável pelo curso',
        ]);
        $logos = PdfLetterhead::make($offering->freeCourse->school);
        $pdf = Pdf::loadView('reports.free-course-certificate', ['document' => $document, 'logos' => $logos, 'preview' => true])->setPaper('a4', 'landscape');

        return PdfMetadata::stream($pdf, 'beaba-previa-curso-livre.pdf', 'Prévia de certificado de curso livre — sem registro');
    }

    public function store(Request $request, FreeCourseOffering $offering, FreeCourseCertificate $certificates)
    {
        $this->authorizeOffering($request, $offering);
        if (! $request->filled('registry_book_id') && app(CredentialRegistry::class)->requiresBook($offering->freeCourse->school_id, 'certificate')) {
            throw ValidationException::withMessages(['registry_book_id' => 'Selecione um livro digital aberto para esta emissão.']);
        }
        if ($request->filled('registry_book_id')) {
            $request->merge(['registry_number' => 1, 'registry_folio' => 'Automática', 'registry_book' => 'Livro digital', 'registered_by' => $request->user()->person?->full_name ?: $request->user()->name]);
        }
        $data = $request->validate(['issuance_token' => [Rule::requiredIf($request->filled('registry_book_id')), 'nullable', 'uuid'], 'registry_book_id' => ['nullable', 'integer', 'exists:credential_books,id'], 'participant_ids' => ['required', 'array', 'min:1', 'max:50'], 'participant_ids.*' => ['required', 'integer', 'distinct'],
            'expected_version' => ['required', 'string', 'size:64'], 'registry_book' => ['required', 'string', 'max:50'],
            'registry_folio' => ['required', 'string', 'max:50'], 'registry_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'issued_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cuiaba')->toDateString()],
            'issued_place' => ['required', 'string', 'max:150'], 'registered_by' => ['required', 'string', 'max:150'],
            'director_name' => ['required', 'string', 'max:150'], 'instructor_name' => ['required', 'string', 'max:150'],
            'instructor_role' => ['required', 'string', 'max:80']]);
        foreach (['registered_by', 'director_name', 'instructor_name'] as $field) {
            if (! FreeCourseCertificate::fullName($data[$field])) {
                throw ValidationException::withMessages([$field => 'Informe o nome completo, sem abreviações.']);
            }
        }
        $paths = [];
        try {
            $batch = DB::transaction(function () use ($request, $offering, $certificates, $data, &$paths): string {
                $course = $offering->freeCourse()->lockForUpdate()->firstOrFail();
                $offering = FreeCourseOffering::whereKey($offering->id)->lockForUpdate()->firstOrFail();
                $offering->setRelation('freeCourse', $course);
                $participants = $offering->participants()->whereIn('id', $data['participant_ids'])->orderBy('id')->lockForUpdate()->with('person')->get();
                if (! hash_equals($certificates->version($offering), $data['expected_version'])) {
                    throw ValidationException::withMessages(['document' => 'O cadastro mudou. Atualize esta página e confira os dados antes de emitir.']);
                }
                if ($participants->count() !== count($data['participant_ids']) || $participants->contains(fn ($p) => ! $certificates->eligible($p))) {
                    throw ValidationException::withMessages(['participant_ids' => 'Selecione somente participantes desta turma com conclusão confirmada e período encerrado.']);
                }
                $logos = PdfLetterhead::make($course->school);
                if (! $logos['maintainer_logo'] || ! $logos['school_logo']) {
                    throw ValidationException::withMessages(['document' => 'Cadastre o logo da escola emissora e confira o logo do Centro Técnico antes da emissão.']);
                }
                foreach (['name', 'cnpj', 'address', 'city', 'state'] as $field) {
                    if (blank($course->school->{$field})) {
                        throw ValidationException::withMessages(['document' => 'Complete nome, CNPJ e endereço na ficha da escola antes de emitir.']);
                    }
                }
                $batch = (string) Str::uuid();
                foreach ($participants as $index => $participant) {
                    if ($missing = $certificates->missingIdentity($participant->person)) {
                        throw ValidationException::withMessages(['document' => 'Complete a ficha de '.$participant->person->full_name.': '.implode(', ', $missing).'.']);
                    }
                    if ($data['issued_date'] < $participant->completed_at->toDateString()) {
                        throw ValidationException::withMessages(['document' => 'Confira os nomes completos dos participantes e a data de emissão, que deve ser posterior ou igual à conclusão.']);
                    }
                    $emit = function (array $override = []) use ($request, $data, $index, $course, $offering, $participant, $logos, $batch, &$paths) {
                        $registration = array_replace($data, $override);
                        unset($registration['participant_ids'], $registration['expected_version'], $registration['registry_book_id'], $registration['issuance_token']);
                        $registration['registry_number'] = $override['registry_number'] ?? (int) $data['registry_number'] + $index;
                        $key = hash('sha256', implode('|', [$course->school_id, 'free-course-certificate', mb_strtolower(trim($registration['registry_book'])), mb_strtolower((string) $registration['registry_number'])]));
                        if (IssuedDocument::where('registration_key', $key)->exists()) {
                            throw ValidationException::withMessages(['registry_number' => 'Há um número deste lote já registrado neste livro. Consulte os originais arquivados ou escolha números disponíveis.']);
                        }
                        $document = IssuedDocument::create(['uuid' => (string) Str::uuid(), 'verification_code' => Str::upper(Str::random(12)),
                            'type' => 'free-course-certificate', 'person_id' => $participant->person_id, 'school_id' => $course->school_id,
                            'issued_by_user_id' => $request->user()->id, 'issued_at' => now(), 'batch_uuid' => $batch, 'registration_key' => $key,
                            'payload' => $this->payload($offering, $participant, $registration)]);
                        $pdf = Pdf::loadView('reports.free-course-certificate', ['document' => $document, 'logos' => $logos, 'preview' => false])->setPaper('a4', 'landscape');
                        $bytes = PdfMetadata::stream($pdf, 'certificado.pdf', 'Certificado de curso livre')->getContent();
                        $path = 'credentials/'.$document->uuid.'.pdf';
                        $paths[] = $path;
                        if (! Storage::disk('local')->put($path, $bytes)) {
                            throw new \RuntimeException('Não foi possível arquivar o certificado. Nenhuma emissão foi concluída.');
                        }
                        chmod(Storage::disk('local')->path($path), 0600);
                        $document->update(['file_path' => $path, 'file_sha256' => hash('sha256', $bytes)]);

                        return $document;
                    };
                    if (! empty($data['registry_book_id'])) {
                        $saved = app(CredentialRegistry::class)->issue((int) $data['registry_book_id'], $course->school_id, 'free-course-certificate', $request->user(), $data['issued_date'], null, $emit, $data['issuance_token'].':'.$participant->id);
                        $batch = $saved->batch_uuid;
                    } else {
                        $emit();
                    }
                }

                return $batch;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return redirect()->route('credential-archive.batch', $batch)->with('status', 'Certificados emitidos e PDFs originais arquivados nas fichas dos participantes.');
    }

    private function payload(FreeCourseOffering $offering, $participant, array $registration): array
    {
        $course = $offering->freeCourse;
        $school = $course->school;

        return ['title' => 'Certificado de curso livre — '.$course->name, 'offering_id' => $offering->id, 'course_id' => $course->id,
            'participant_name' => $participant->person->full_name, 'course_name' => $course->name, 'workload_hours' => $course->workload_hours,
            'participant_identity' => ['cpf' => $participant->person->cpf, 'birth_date' => $participant->person->birth_date?->toDateString(),
                'birth_city' => $participant->person->birth_city, 'birth_state' => $participant->person->birth_state, 'nationality' => $participant->person->nationality],
            'syllabus' => $course->syllabus, 'offering_name' => $offering->name, 'starts_at' => $offering->starts_at->toDateString(),
            'ends_at' => $offering->ends_at->toDateString(), 'delivery_mode' => FreeCourseOffering::MODES[$offering->delivery_mode],
            'location' => $offering->location, 'school_name' => $school->name, 'school_cnpj' => $school->cnpj,
            'school_address' => $school->documentAddress(), 'maintainer' => $school->maintainer,
            'registration' => $registration];
    }
}
