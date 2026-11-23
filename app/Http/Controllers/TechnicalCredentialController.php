<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\PersonSchoolRole;
use App\Models\StudentEnrollment;
use App\Support\CredentialRegistry;
use App\Support\PdfMetadata;
use App\Support\SchoolSignatureStaff;
use App\Support\TechnicalCredentialEligibility;
use App\Support\TechnicalEnrollmentOffer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechnicalCredentialController extends Controller
{
    public const FIELDS = ['registry_number' => 'Número do registro', 'registry_book' => 'Livro do registro', 'registry_folio' => 'Folha do registro',
        'registered_by' => 'Responsável pelo registro', 'secretary_name' => 'Nome do(a) secretário(a)', 'director_name' => 'Nome do(a) diretor(a)', 'issued_place' => 'Local de emissão'];

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
        $staff = SchoolSignatureStaff::forSchool($school);
        $defaults = ['secretary_name' => $staff[PersonSchoolRole::POSITION_SECRETARY]?->full_name,
            'director_name' => $staff[PersonSchoolRole::POSITION_DIRECTOR]?->full_name,
            'issued_place' => collect([$school->district, $school->city.'-'.$school->state])->filter()->join(', ')];

        return view('technical-credentials.prepare', ['enrollment' => $enrollment, 'course' => $course, 'courses' => $courses,
            'requiresBook' => app(CredentialRegistry::class)->requiresBook($school->id, $kind),
            'books' => app(CredentialRegistry::class)->books($request->user(), $school->id, $kind)->whereNull('closed_at')->orderBy('identifier')->get(),
            'kind' => $kind, 'program' => $course->technicalProgram, 'state' => $state, 'missing' => $this->readiness($enrollment, $course),
            'fields' => self::FIELDS, 'defaults' => $defaults, 'offerForm' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course),
            'recent' => IssuedDocument::where('person_id', $enrollment->person_id)->where('school_id', $school->id)
                ->whereIn('type', ['technical-intermediate-certificate', 'technical-course-diploma', 'technical-course-diploma-preview'])->latest('id')->limit(15)->get()]);
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
        if (! $request->filled('registry_book_id') && app(CredentialRegistry::class)->requiresBook($course->academicYear->school_id, $kind)) {
            throw ValidationException::withMessages(['registry_book_id' => 'Selecione um livro digital aberto para esta emissão.']);
        }
        if ($request->filled('registry_book_id')) {
            $request->merge(['registry_number' => 'Automático', 'registry_folio' => 'Automática', 'registry_book' => 'Livro digital', 'registered_by' => $request->user()->person?->full_name ?: $request->user()->name]);
        }
        $rules = ['issuance_token' => [Rule::requiredIf($request->filled('registry_book_id')), 'nullable', 'uuid'], 'registry_book_id' => ['nullable', 'integer', 'exists:credential_books,id'],
            'sistec_code' => [Rule::requiredIf($kind === 'diploma' && $state['final']), 'nullable', 'string', 'max:100', 'not_regex:/[<>\r\n]/'], 'course_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(['certificate', 'diploma'])],
            'issued_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cuiaba')->toDateString()],
            'registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:issued_date'],
            'history_reference' => ['nullable', Rule::requiredIf($request->filled('registry_book_id')), 'string', 'max:200'],
            'observations' => ['nullable', 'string', 'max:500']];
        foreach (self::FIELDS as $field => $label) {
            if ($request->filled('registry_book_id') && in_array($field, ['director_name', 'secretary_name'], true)) {
                continue;
            }
            $rules[$field] = ['required', 'string', 'max:'.(str_starts_with($field, 'registry_') ? 50 : 150), 'not_regex:/[<>\r\n]/'];
        }
        foreach (['secretary_name', 'director_name', 'registered_by'] as $field) {
            if (! isset($rules[$field])) {
                continue;
            }
            $rules[$field][] = function ($attribute, $value, $fail): void {
                if (! self::fullName($value)) {
                    $fail('Informe o nome completo, sem abreviações: '.(self::FIELDS[$attribute] ?? $attribute).'.');
                }
            };
        }
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
        if ($request->filled('registry_book_id') && $final) {
            $staff = SchoolSignatureStaff::forSchool($school, $data['issued_date']);
            foreach (['director_name' => PersonSchoolRole::POSITION_DIRECTOR, 'secretary_name' => PersonSchoolRole::POSITION_SECRETARY] as $field => $position) {
                if (! $staff[$position]) {
                    throw ValidationException::withMessages([$field => 'Cadastre o responsável ou a substituição vigente para a data da emissão.']);
                }
                $data[$field] = $staff[$position]->full_name;
            }
        }
        $student = $enrollment->student;
        $type = $kind === 'certificate' ? 'technical-intermediate-certificate' : ($final ? 'technical-course-diploma' : 'technical-course-diploma-preview');
        $registryKey = $final ? hash('sha256', implode('|', [$school->id, $kind, mb_strtolower(trim($data['registry_book'])), mb_strtolower(trim($data['registry_number']))])) : null;
        $rows = $module ? $module['components'] : collect($state['modules'])->flatMap(fn ($item) => $item['components'])->values()->all();
        $payload = ['title' => $kind === 'certificate' ? 'Certificado Técnico Intermediário' : ($final ? 'Diploma de Curso Técnico' : 'Diploma de Curso Técnico — AINDA NÃO FINALIZADO'),
            'kind' => $kind, 'is_final' => $final, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id,
            'program_id' => $program->id, 'program' => $program->documentData(), 'module' => $module ? collect($module)->except('issues')->all() : null,
            'components' => $rows, 'total_hours' => $module['hours'] ?? $program->totalHours(), 'completed_at' => $final ? $completion : null,
            'offer_form' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course), 'status_reasons' => $final ? [] : $state['reasons'],
            'student' => $student->only(['full_name', 'birth_date', 'nationality', 'birth_city', 'birth_state', 'mother_name', 'father_name']) + ['photo_identification' => $student->schoolPhotoIdentification()],
            'school_name' => $school->name, 'school_cnpj' => $school->cnpj, 'registration' => collect($data)->except(['course_id', 'kind', 'module_key', 'registry_book_id', 'issuance_token'])->all()];
        $path = null;
        $response = null;
        try {
            DB::transaction(function () use ($request, $enrollment, $school, $type, $payload, &$registryKey, &$path, &$response, $data, $final) {
                $emit = function (array $registration = []) use ($request, $enrollment, $school, $type, $payload, &$registryKey, &$path, &$response) {
                    $payload['registration'] = array_replace($payload['registration'], $registration);
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
                if (! empty($data['registry_book_id']) && $final) {
                    $saved = app(CredentialRegistry::class)->issue((int) $data['registry_book_id'], $school->id, $type, $request->user(), $data['registration_date'], $data['sistec_code'] ?? null, $emit, $data['issuance_token']);
                    $response = redirect()->route('credential-registry.entry', $saved->registration);
                } else {
                    $emit();
                }
            });

            return $response;
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
