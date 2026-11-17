<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\PersonSchoolRole;
use App\Models\StudentEnrollment;
use App\Support\PdfMetadata;
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
        $staff = $school->roles()->with('person')->where('active', true)->get()->filter(fn ($r) => $r->isActiveForDate());
        $defaults = ['secretary_name' => $staff->firstWhere('position', PersonSchoolRole::POSITION_SECRETARY)?->person?->full_name,
            'director_name' => $staff->firstWhere('position', PersonSchoolRole::POSITION_DIRECTOR)?->person?->full_name,
            'issued_place' => collect([$school->district, $school->city.'-'.$school->state])->filter()->join(', ')];

        return view('technical-credentials.prepare', ['enrollment' => $enrollment, 'course' => $course, 'courses' => $courses,
            'kind' => $kind, 'program' => $course->technicalProgram, 'state' => $state, 'missing' => $this->readiness($enrollment, $course),
            'fields' => self::FIELDS, 'defaults' => $defaults, 'offerForm' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course),
            'recent' => IssuedDocument::where('person_id', $enrollment->person_id)->where('school_id', $school->id)
                ->whereIn('type', ['technical-intermediate-certificate', 'technical-course-diploma', 'technical-course-diploma-preview'])->latest('id')->limit(15)->get()]);
    }

    public function store(Request $request, StudentEnrollment $enrollment, TechnicalCredentialEligibility $eligibility)
    {
        [$course, $courses, $kind] = $this->context($request, $enrollment);
        $missing = $this->readiness($enrollment, $course);
        if ($missing) {
            throw ValidationException::withMessages(['document' => 'Complete os dados antes da emissão: '.implode(', ', $missing).'.']);
        }
        $state = $eligibility->evaluate($enrollment, $course);
        $rules = ['course_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(['certificate', 'diploma'])],
            'issued_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cuiaba')->toDateString()],
            'registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:issued_date'],
            'observations' => ['nullable', 'string', 'max:500']];
        foreach (self::FIELDS as $field => $label) {
            $rules[$field] = ['required', 'string', 'max:'.(str_starts_with($field, 'registry_') ? 50 : 150), 'not_regex:/[<>\r\n]/'];
        }
        foreach (['secretary_name', 'director_name', 'registered_by'] as $field) {
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
        $student = $enrollment->student;
        $type = $kind === 'certificate' ? 'technical-intermediate-certificate' : ($final ? 'technical-course-diploma' : 'technical-course-diploma-preview');
        $registryKey = $final ? hash('sha256', implode('|', [$school->id, $kind, mb_strtolower(trim($data['registry_book'])), mb_strtolower(trim($data['registry_number']))])) : null;
        $rows = $module ? $module['components'] : collect($state['modules'])->flatMap(fn ($item) => $item['components'])->values()->all();
        $payload = ['title' => $kind === 'certificate' ? 'Certificado Técnico Intermediário' : ($final ? 'Diploma de Curso Técnico' : 'Diploma de Curso Técnico — AINDA NÃO FINALIZADO'),
            'kind' => $kind, 'is_final' => $final, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id,
            'program_id' => $program->id, 'program' => $program->toArray(), 'module' => $module ? collect($module)->except('issues')->all() : null,
            'components' => $rows, 'total_hours' => $module['hours'] ?? $program->totalHours(), 'completed_at' => $final ? $completion : null,
            'offer_form' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course), 'status_reasons' => $final ? [] : $state['reasons'],
            'student' => $student->only(['full_name', 'birth_date', 'nationality', 'birth_city', 'birth_state', 'mother_name', 'father_name']),
            'school_name' => $school->name, 'school_cnpj' => $school->cnpj, 'registration' => collect($data)->except(['course_id', 'kind', 'module_key'])->all()];
        $path = null;
        try {
            return DB::transaction(function () use ($request, $enrollment, $school, $type, $payload, $registryKey, &$path) {
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
                $document->update(['file_path' => $path]);

                return $response;
            });
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
            abort_unless($module && filled($module['qualification']) && $module['eligible'], 422, 'A prévia deste certificado só está disponível após a conclusão do módulo e o cumprimento dos requisitos.');
        }
        abort_unless($enrollment->student->birth_date, 422, 'Complete a data de nascimento do estudante antes de visualizar o documento.');
        $school = $course->academicYear->school;
        $staff = $school->roles()->with('person')->where('active', true)->get()->filter(fn ($role) => $role->isActiveForDate());
        $rows = $module ? $module['components'] : collect($state['modules'])->flatMap(fn ($item) => $item['components'])->values()->all();
        $data = ['layout_preview' => true, 'kind' => $kind, 'is_final' => $kind === 'certificate' || $state['final'],
            'program' => $course->technicalProgram->toArray(), 'module' => $module, 'components' => $rows,
            'total_hours' => $module['hours'] ?? $course->technicalProgram->totalHours(),
            'completed_at' => $module['completed_at'] ?? $state['completed_at'],
            'offer_form' => app(TechnicalEnrollmentOffer::class)->resolve($enrollment, $course) ?: 'forma de oferta pendente na matrícula',
            'student' => $enrollment->student->only(['full_name', 'birth_date', 'nationality', 'birth_city', 'birth_state', 'mother_name', 'father_name']),
            'school_name' => $school->name, 'school_cnpj' => $school->cnpj,
            'preview_url' => route('technical-credentials.preview', ['enrollment' => $enrollment->id, 'course_id' => $course->id, 'kind' => $kind, 'module_key' => $module['key'] ?? null]),
            'registration' => ['registry_number' => '', 'registry_book' => '', 'registry_folio' => '', 'registered_by' => '', 'registration_date' => '', 'observations' => '',
                'secretary_name' => $staff->firstWhere('position', PersonSchoolRole::POSITION_SECRETARY)?->person?->full_name ?: 'Nome completo a preencher',
                'director_name' => $staff->firstWhere('position', PersonSchoolRole::POSITION_DIRECTOR)?->person?->full_name ?: 'Nome completo a preencher',
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
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($document->school_id), 403);
        abort_unless(in_array($document->type, ['technical-intermediate-certificate', 'technical-course-diploma', 'technical-course-diploma-preview'], true), 404);
        abort_if($document->revoked_at, 410);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return response(Storage::disk('local')->get($document->file_path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="beaba-documento-tecnico-'.$document->uuid.'.pdf"']);
    }
}
