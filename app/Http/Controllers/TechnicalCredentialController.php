<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\PersonSchoolRole;
use App\Models\StudentEnrollment;
use App\Support\CredentialRegistry;
use App\Support\PdfMetadata;
use App\Support\SchoolRecordPdf;
use App\Support\SchoolSignatureStaff;
use App\Support\TechnicalCredentialEligibility;
use App\Support\TechnicalEnrollmentOffer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechnicalCredentialController extends Controller
{
    private function context(Request $request, StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing('student', 'courses.technicalProgram', 'schoolClass.academicYear.school');
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($enrollment->schoolClass->academicYear->school_id), 403);
        $courses = $enrollment->courses->where('stage', 'tecnico')->whereNotNull('technical_program_id');
        abort_if($courses->isEmpty(), 404);
        $course = $request->filled('course_id') ? $courses->firstWhere('id', $request->integer('course_id')) : $courses->first();
        abort_unless($course && $course->technicalProgram->school_id === $enrollment->schoolClass->academicYear->school_id, 404);
        $kind = $request->input('kind', 'diploma');
        abort_unless(in_array($kind, ['certificate', 'diploma'], true), 404);

        return [$course, $courses, $kind];
    }

    private function readiness(StudentEnrollment $enrollment, AcademicCourse $course): array
    {
        $student = $enrollment->student;
        $missing = $course->technicalProgram->missingDocumentFields();
        foreach (['full_name' => 'nome do estudante', 'birth_date' => 'nascimento', 'birth_city' => 'naturalidade', 'birth_state' => 'UF da naturalidade', 'nationality' => 'nacionalidade'] as $field => $label) {
            if (blank($student->{$field})) {
                $missing[] = $label;
            }
        }
        if (blank($student->mother_name) && blank($student->father_name)) {
            $missing[] = 'filiação';
        }
        foreach (['full_name' => 'nome do estudante', 'mother_name' => 'nome da mãe', 'father_name' => 'nome do pai'] as $field => $label) {
            if (filled($student->{$field}) && ! self::fullName($student->{$field})) {
                $missing[] = $label.' completo, sem abreviações';
            }
        }
        foreach (['address' => 'endereço da escola', 'city' => 'cidade da escola', 'state' => 'UF da escola', 'postal_code' => 'CEP da escola'] as $field => $label) {
            if (blank($course->academicYear->school->{$field})) {
                $missing[] = $label;
            }
        }
        if (blank($course->academicYear->school->cnpj)) {
            $missing[] = 'CNPJ da escola';
        }
        if (! app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course)) {
            $missing[] = 'forma de oferta na matrícula (concomitante/subsequente)';
        }

        return $missing;
    }

    public function prepare(Request $request, StudentEnrollment $enrollment, TechnicalCredentialEligibility $eligibility)
    {
        [$course, $courses, $kind] = $this->context($request, $enrollment);
        $state = $eligibility->evaluate($enrollment, $course);
        $school = $enrollment->schoolClass->academicYear->school;
        $defaults = ['issued_place' => collect([$school->district, $school->city.'-'.$school->state])->filter()->join(', ')];

        return view('technical-credentials.prepare', ['enrollment' => $enrollment, 'course' => $course, 'courses' => $courses,
            'requiresBook' => true,
            'books' => app(CredentialRegistry::class)->books($request->user(), $school->id, $kind)->whereNull('closed_at')->orderBy('identifier')->get(),
            'kind' => $kind, 'program' => $course->technicalProgram, 'state' => $state, 'missing' => $this->readiness($enrollment, $course),
            'defaults' => $defaults, 'offerForm' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course),
            'recent' => IssuedDocument::where('person_id', $enrollment->person_id)->where('school_id', $school->id)
                ->whereIn('type', ['technical-intermediate-certificate', 'technical-course-diploma', 'technical-course-diploma-preview'])->with('registration')->latest('id')->limit(15)->get()]);
    }

    public function store(Request $request, StudentEnrollment $enrollment, TechnicalCredentialEligibility $eligibility)
    {
        [$course, $courses, $kind] = $this->context($request, $enrollment);
        $state = $eligibility->evaluate($enrollment, $course);
        if ($kind === 'diploma' && ! $state['final']) {
            return $this->preview($request, $enrollment, $eligibility);
        }
        $missing = $this->readiness($enrollment, $course);
        if ($missing) {
            throw ValidationException::withMessages(['document' => 'Complete os dados antes da emissão: '.implode(', ', $missing).'.']);
        }
        if ($kind === 'diploma' && blank($enrollment->student->schoolPhotoIdentification())) {
            throw ValidationException::withMessages(['student' => 'Confira a CIN ou outro documento oficial com foto na ficha do estudante antes de registrar o diploma.']);
        }
        $rules = ['issuance_token' => ['required', 'uuid'], 'registry_book_id' => ['required', 'integer', 'exists:credential_books,id'],
            'sistec_code' => [Rule::requiredIf($kind === 'diploma' && $state['final']), 'nullable', 'string', 'max:100', 'not_regex:/[<>\r\n]/'], 'course_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(['certificate', 'diploma'])],
            'issued_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cuiaba')->toDateString()],
            'registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:issued_date'],
            'history_code' => ['nullable', 'string', 'max:60'],
            'history_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'evidence_reviewed' => ['required', 'accepted'],
            'authorization_pdf' => [Rule::requiredIf($kind === 'diploma'), 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            'sistec_proof' => [Rule::requiredIf($kind === 'diploma'), 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            'high_school_certificate' => [Rule::requiredIf($kind === 'diploma'), 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            'title_gender' => [Rule::requiredIf($kind === 'diploma'), 'nullable', Rule::in(['masculine', 'feminine'])],
            'observations' => ['nullable', 'string', 'max:500']];
        $rules['issued_place'] = ['required', 'string', 'max:150', 'not_regex:/[<>\r\n]/'];
        if ($kind === 'certificate') {
            $rules['module_key'] = ['required', Rule::in(collect($course->technicalProgram->modules)->filter(fn ($m) => filled($m['qualification'] ?? null))->pluck('key')->all())];
        }
        $data = $request->validate($rules);
        $module = $kind === 'certificate' ? ($state['modules'][$data['module_key']] ?? null) : null;
        if ($kind === 'certificate' && (! $module || ! $module['eligible'])) {
            throw ValidationException::withMessages(['module_key' => 'Certificado indisponível: '.implode(' ', $module['issues'] ?? ['Módulo não concluído.'])]);
        }
        $final = $kind === 'certificate' || $state['final'];
        $completion = $module['completed_at'] ?? $state['completed_at'];
        if ($final && ($data['issued_date'] < $completion || $data['registration_date'] < $completion)) {
            throw ValidationException::withMessages(['registration_date' => 'A emissão e o registro não podem ser anteriores à conclusão.']);
        }
        $program = $course->technicalProgram;
        $school = $course->academicYear->school;
        $offerForm = app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course);
        $evidence = [];
        $historyEntry = null;
        if ($kind === 'diploma') {
            $start = $enrollment->enrolled_at?->toDateString();
            if (! $start || ! $program->authorization_starts_at || ! $program->authorization_ends_at || ! filled($program->official_gazette_reference)
                || $start < $program->authorization_starts_at->toDateString() || $start > $program->authorization_ends_at->toDateString()
                || ! in_array($offerForm, $program->offer_modes ?? [], true)) {
                throw ValidationException::withMessages(['authorization_pdf' => 'Confira a referência no Diário Oficial, o período autorizativo e a forma de oferta que se aplica a esta matrícula.']);
            }
            if ($data['issued_date'] > $program->authorization_ends_at->toDateString()) {
                $request->validate(['continuation_proof' => ['required', 'file', 'mimes:pdf', 'max:20480']]);
                $evidence['authorized_continuation'] = $request->file('continuation_proof')->get();
            }
            foreach (['authorization_pdf' => 'authorization_act', 'sistec_proof' => 'sistec_evidence', 'high_school_certificate' => 'high_school_certificate'] as $field => $label) {
                $evidence[$label] = $request->file($field)->get();
            }
        }
        if (filled($data['history_code'] ?? null)) {
            $historyEntry = app(SchoolRecordPdf::class)->signedHistory($data['history_code'], $enrollment->person_id, $school->id, AcademicCourse::STAGE_TECHNICAL, $course->id);
            if (! ($historyEntry->snapshot['course_transcript'] ?? false)
                || (isset($historyEntry->snapshot['module_key']) && $historyEntry->snapshot['module_key'] !== ($module['key'] ?? null))) {
                throw ValidationException::withMessages(['history_code' => 'Use o histórico para certificação que abrange este módulo ou o curso completo.']);
            }
        } else {
            $request->validate(['history_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480']]);
            $evidence['signed_course_history'] = $request->file('history_pdf')->get();
        }
        $staff = SchoolSignatureStaff::forSchool($school, $data['issued_date']);
        foreach (['director_name' => PersonSchoolRole::POSITION_DIRECTOR, 'secretary_name' => PersonSchoolRole::POSITION_SECRETARY] as $field => $position) {
            if (! $staff[$position] || ! self::fullName($staff[$position]->full_name)) {
                throw ValidationException::withMessages([$field => 'Cadastre o responsável ou a substituição vigente para a data da emissão.']);
            }
            $data[$field] = $staff[$position]->full_name;
        }
        if ($kind === 'diploma') {
            $registrar = SchoolSignatureStaff::forSchool($school, $data['registration_date'])[PersonSchoolRole::POSITION_SECRETARY];
            if (! $registrar) {
                throw ValidationException::withMessages(['registration_date' => 'Confira a designação da secretaria na data do registro.']);
            }
            $data['registered_by'] = $registrar->full_name;
        }
        $student = $enrollment->student;
        $type = $kind === 'certificate' ? 'technical-intermediate-certificate' : 'technical-course-diploma';
        $registryKey = null;
        $rows = $module ? $module['components'] : collect($state['modules'])->flatMap(fn ($item) => $item['components'])->values()->all();
        $programData = $program->documentData();
        if ($kind === 'diploma') {
            $programData['graduate_title'] = preg_replace('/^Técnic[oa]\b/ui', $data['title_gender'] === 'feminine' ? 'Técnica' : 'Técnico', $programData['graduate_title']);
        }
        $payload = ['title' => $kind === 'certificate' ? 'Certificado Técnico Intermediário' : ($final ? 'Diploma de Curso Técnico' : 'Diploma de Curso Técnico — AINDA NÃO FINALIZADO'),
            'kind' => $kind, 'is_final' => $final, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id,
            'program_id' => $program->id, 'program' => $programData, 'module' => $module ? collect($module)->except('issues')->all() : null,
            'components' => $rows, 'total_hours' => $module['hours'] ?? $program->totalHours(), 'completed_at' => $final ? $completion : null,
            'offer_form' => $offerForm, 'status_reasons' => $final ? [] : $state['reasons'],
            'student' => $student->only(['full_name', 'birth_date', 'nationality', 'birth_city', 'birth_state', 'mother_name', 'father_name']) + ['photo_identification' => $student->schoolPhotoIdentification()],
            'school_name' => $school->name, 'school_cnpj' => $school->cnpj,
            'history_registration_id' => $historyEntry?->id,
            'authorization_dates' => ['starts_at' => $program->authorization_starts_at?->toDateString(), 'ends_at' => $program->authorization_ends_at?->toDateString()],
            'registration' => collect($data)->except(['course_id', 'kind', 'module_key', 'registry_book_id', 'issuance_token',
                'history_code', 'history_pdf', 'authorization_pdf', 'sistec_proof', 'high_school_certificate', 'evidence_reviewed', 'title_gender'])->all()];
        $path = null;
        try {
            $emit = function (array $registration) use ($request, $enrollment, $school, $type, $payload, &$registryKey, &$path) {
                $appointedRegistrar = $payload['registration']['registered_by'] ?? null;
                $payload['registration'] = array_replace($payload['registration'], $registration);
                if ($type === 'technical-course-diploma') {
                    $payload['registration']['registered_by'] = $appointedRegistrar;
                }
                if (IssuedDocument::query()->where('school_id', $school->id)->where('type', $type)
                    ->whereNull('revoked_at')->where('payload->enrollment_id', $enrollment->id)
                    ->where('payload->course_id', $payload['course_id'])
                    ->when($payload['module'], fn ($query) => $query->where('payload->module->key', $payload['module']['key']))
                    ->exists()) {
                    throw ValidationException::withMessages(['registry_number' => 'Já existe uma via emitida para esta matrícula. Consulte o original para segunda via ou correção.']);
                }
                $r = $payload['registration'];
                $registryKey = ($payload['is_final'] ?? false) ? hash('sha256', implode('|', [$school->id, $payload['kind'], mb_strtolower(trim($r['registry_book'])), mb_strtolower(trim($r['registry_number']))])) : null;
                $document = IssuedDocument::create(['uuid' => (string) Str::uuid(), 'verification_code' => Str::upper(Str::random(12)), 'type' => $type,
                    'person_id' => $enrollment->person_id, 'school_id' => $school->id, 'issued_by_user_id' => $request->user()->id,
                    'payload' => $payload, 'registration_key' => $registryKey, 'issued_at' => now()]);
                $pdf = Pdf::loadView('reports.technical-credential', ['data' => $payload, 'document' => $document])->setPaper('a4', 'landscape');
                $response = PdfMetadata::stream($pdf, 'beaba-'.$type.'-'.$document->uuid.'.pdf', $payload['title']);
                $path = 'technical-credentials/'.$document->uuid.'.pdf';
                if (! Storage::disk('local')->put($path, $response->getContent())) {
                    throw new \RuntimeException('Não foi possível guardar o documento emitido.');
                }
                chmod(Storage::disk('local')->path($path), 0600);
                $document->update(['file_path' => $path, 'file_sha256' => hash('sha256', $response->getContent())]);

                return $document;
            };
            $saved = app(CredentialRegistry::class)->issue((int) $data['registry_book_id'], $school->id, $type, $request->user(), $data['registration_date'], $data['sistec_code'] ?? null, $emit,
                $data['issuance_token'].':'.$enrollment->id.':'.$payload['course_id'].':'.($data['module_key'] ?? ''), evidence: $evidence);

            return redirect()->route('credential-registry.entry', $saved->registration);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($e instanceof QueryException && IssuedDocument::where('registration_key', $registryKey)->whereNotNull('registration_key')->exists()) {
                throw ValidationException::withMessages(['registry_number' => 'Este número já foi usado neste livro para este tipo de documento. Use a reimpressão do documento já emitido.']);
            }
            throw $e;
        }
    }

    public function preview(Request $request, StudentEnrollment $enrollment, TechnicalCredentialEligibility $eligibility)
    {
        [$course, $courses, $kind] = $this->context($request, $enrollment);
        $state = $eligibility->evaluate($enrollment, $course);
        $module = $kind === 'certificate' ? ($state['modules'][$request->input('module_key')] ?? null) : null;
        if ($kind === 'certificate') {
            abort_unless($module && filled($module['qualification']), 422, 'Selecione o módulo da prévia.');
        }
        $school = $course->academicYear->school;
        $staff = SchoolSignatureStaff::forSchool($school);
        $rows = $module ? $module['components'] : collect($state['modules'])->flatMap(fn ($item) => $item['components'])->values()->all();
        $data = ['layout_preview' => true, 'kind' => $kind, 'is_final' => ($kind === 'certificate' ? $module['eligible'] : $state['final']),
            'program' => $course->technicalProgram->documentData(), 'module' => $module, 'components' => $rows,
            'total_hours' => $module['hours'] ?? $course->technicalProgram->totalHours(),
            'completed_at' => $module['completed_at'] ?? $state['completed_at'],
            'offer_form' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course) ?: 'forma de oferta pendente na matrícula',
            'student' => $enrollment->student->only(['full_name', 'birth_date', 'nationality', 'birth_city', 'birth_state', 'mother_name', 'father_name']) + ['photo_identification' => $enrollment->student->schoolPhotoIdentification()],
            'school_name' => $school->name, 'school_cnpj' => $school->cnpj,
            'preview_url' => route('technical-credentials.preview', ['enrollment' => $enrollment->id, 'course_id' => $course->id, 'kind' => $kind, 'module_key' => $module['key'] ?? null]),
            'registration' => ['registry_number' => '', 'registry_book' => '', 'registry_folio' => '', 'registered_by' => '', 'registration_date' => '', 'observations' => '',
                'secretary_name' => $staff[PersonSchoolRole::POSITION_SECRETARY]?->full_name ?: 'Nome completo a preencher',
                'director_name' => $staff[PersonSchoolRole::POSITION_DIRECTOR]?->full_name ?: 'Nome completo a preencher',
                'issued_place' => collect([$school->district, $school->city.'-'.$school->state])->filter()->join(', '), 'issued_date' => now('America/Cuiaba')->toDateString()]];
        $document = new IssuedDocument(['verification_code' => 'PREVIA-SEM-REGISTRO']);
        $pdf = Pdf::loadView('reports.technical-credential', compact('data', 'document'))->setPaper('a4', 'landscape');

        return PdfMetadata::stream($pdf, 'beaba-previa-'.$kind.'-'.$enrollment->id.'.pdf', 'Prévia para conferência — sem registro');
    }

    public function courseHistory(Request $request, StudentEnrollment $enrollment, TechnicalCredentialEligibility $eligibility, SchoolRecordPdf $records)
    {
        [$course] = $this->context($request, $enrollment);
        $program = $course->technicalProgram;
        $school = $course->academicYear->school;
        $state = $eligibility->evaluate($enrollment, $course);
        $modules = collect($state['modules']);
        $title = 'Histórico para certificação · '.$program->name;
        $type = 'student-academic-history';
        $payload = [
            'title' => $title, 'education_stage' => AcademicCourse::STAGE_TECHNICAL, 'technical_course_id' => $course->id,
            'enrollment_id' => $enrollment->id, 'student' => ['full_name' => $enrollment->student->full_name],
        ];
        $render = function (IssuedDocument $document) use ($enrollment, $course, $modules, $title, $school) {
            $section = isset($document->payload['module_key']) ? $modules->only([$document->payload['module_key']]) : $modules;
            $pdf = Pdf::loadView('reports.technical-course-history', [
                'enrollment' => $enrollment, 'course' => $course, 'modules' => $section, 'title' => $title,
                'issuedDocument' => $document, 'letterhead' => PdfLetterhead::make($school, AcademicCourse::STAGE_TECHNICAL),
            ])->setPaper('a4', 'portrait');

            return PdfMetadata::stream($pdf, 'beaba-historico-tecnico-'.$course->id.'-'.$enrollment->id.'.pdf');
        };
        $parameters = ['enrollment' => $enrollment, 'course_id' => $course->id];
        if ($request->isMethod('get')) {
            if ($request->boolean('preview')) {
                return $render($records->draft($request->user(), $school, $type, $enrollment->person_id, $payload));
            }
            return view('credential-registry.prepare-record', [
                'title' => $title, 'context' => $enrollment->student->full_name,
                'notice' => 'Esta via acompanha o diploma ou o certificado técnico. Use o perfil profissional e a prática previstos no plano aprovado.',
                'action' => route('technical-credentials.history.register', $parameters),
                'preview' => route('technical-credentials.history.prepare', $parameters + ['preview' => 1]),
                'books' => app(CredentialRegistry::class)->books($request->user(), $school->id, 'history')->whereNull('closed_at')->get(),
                'technicalModules' => $modules->filter(fn ($module) => $module['eligible'] && filled($module['qualification'])),
            ]);
        }
        $data = $request->validate(SchoolRecordPdf::RULES + [
            'module_key' => ['nullable', Rule::in($modules->keys()->all())],
            'professional_profile' => ['required', 'string', 'max:5000'],
            'professional_practice' => ['required', 'string', 'max:3000'],
        ]);
        $module = $data['module_key'] ?? null;
        $eligible = $module ? $modules[$module]['eligible'] && filled($modules[$module]['qualification'])
            : $modules->isNotEmpty() && $modules->every(fn ($item) => $item['eligible']);
        if (! $eligible || blank($enrollment->student->schoolPhotoIdentification()) || $this->readiness($enrollment, $course)) {
            throw ValidationException::withMessages(['document' => 'Confira a conclusão dos módulos, a identificação do estudante e os dados oficiais do curso.']);
        }
        $staff = $records->signers($school);
        $payload += [
            'signers' => ['director' => $staff['diretor']->full_name, 'secretary' => $staff['secretário']->full_name],
            'module_key' => $module, 'professional_profile' => $data['professional_profile'],
            'professional_practice' => $data['professional_practice'], 'course_transcript' => true,
        ];
        $document = $records->issue($request->user(), $school, $type, $enrollment->person_id, $payload, $data, $render);

        return redirect()->route('credential-registry.entry', $document->registration);
    }

    private static function fullName(string $value): bool
    {
        return count(preg_split('/\s+/u', trim($value))) >= 2 && ! str_contains($value, '.')
            && ! preg_match('/(?:^|\s)[\p{L}]\.?(?=\s|$)/u', preg_replace('/\b[eE]\b/u', 'e-e', $value));
    }

    public function pdf(Request $request, IssuedDocument $document)
    {
        return app(CredentialArchiveController::class)->pdf($request, $document);
    }
}
