<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DataQualityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return view('data-quality.index', $this->qualityData($request));
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $data = $this->qualityData($request);
        $selectedSchool = $data['selectedSchoolId']
            ? $data['schools']->firstWhere('id', $data['selectedSchoolId'])
            : null;
        $issuedDocument = $this->issuedDocument($request, $data, $selectedSchool);

        $pdf = Pdf::loadView('reports.data-quality', $data + [
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($selectedSchool),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-conformidade-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function qualityData(Request $request): array
    {
        $schools = $this->availableSchools($request);
        $selectedSchoolId = $this->selectedSchoolId($request, $schools);
        $schoolIds = $this->schoolIdsForChecks($request, $schools, $selectedSchoolId);
        $selectedSeverity = $this->selectedSeverity($request);

        $personChecks = $this->personChecks($schoolIds);
        $roleChecks = $this->roleChecks($schoolIds);
        $contactChecks = $this->contactChecks($schoolIds);
        $schoolChecks = $this->schoolChecks($schoolIds);
        $academicChecks = $this->academicChecks($schoolIds);

        $groups = collect([
            ['title' => 'Pessoas', 'description' => 'Dados civis, login e acesso aos documentos.', 'checks' => $personChecks, 'icon' => 'fa-users'],
            ['title' => 'Vínculos', 'description' => 'Papéis, escolas e períodos de vínculo.', 'checks' => $roleChecks, 'icon' => 'fa-id-badge'],
            ['title' => 'Responsáveis e contatos', 'description' => 'Responsáveis legais e canais de contato.', 'checks' => $contactChecks, 'icon' => 'fa-address-book'],
            ['title' => 'Escolas e documentos', 'description' => 'Papel timbrado, identificação oficial e dados da unidade.', 'checks' => $schoolChecks, 'icon' => 'fa-school'],
            ['title' => 'Rotina acadêmica', 'description' => 'Ano letivo, matrículas, turmas e regras necessárias.', 'checks' => $academicChecks, 'icon' => 'fa-book-open'],
        ]);

        $summary = [
            'total' => $groups->sum(fn (array $group): int => (int) $group['checks']->sum('count')),
            'danger' => $groups->sum(fn (array $group): int => (int) $group['checks']->where('severity', 'danger')->sum('count')),
            'warning' => $groups->sum(fn (array $group): int => (int) $group['checks']->where('severity', 'warning')->sum('count')),
            'info' => $groups->sum(fn (array $group): int => (int) $group['checks']->where('severity', 'info')->sum('count')),
        ];

        $displayGroups = $selectedSeverity
            ? $groups
                ->map(function (array $group) use ($selectedSeverity): array {
                    $group['checks'] = $group['checks']->where('severity', $selectedSeverity)->values();

                    return $group;
                })
                ->filter(fn (array $group): bool => $group['checks']->isNotEmpty())
                ->values()
            : $groups;

        return [
            'personChecks' => $personChecks,
            'roleChecks' => $roleChecks,
            'contactChecks' => $contactChecks,
            'schoolChecks' => $schoolChecks,
            'academicChecks' => $academicChecks,
            'groups' => $groups,
            'displayGroups' => $displayGroups,
            'summary' => $summary,
            'workflows' => $this->workflows($personChecks, $contactChecks, $schoolChecks, $academicChecks),
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'selectedSeverity' => $selectedSeverity,
        ];
    }

    private function selectedSeverity(Request $request): ?string
    {
        $severity = $request->query('severity');

        return in_array($severity, ['danger', 'warning', 'info'], true) ? (string) $severity : null;
    }

    /**
     * @param Collection<int, array<string, mixed>> $personChecks
     * @param Collection<int, array<string, mixed>> $contactChecks
     * @param Collection<int, array<string, mixed>> $schoolChecks
     * @param Collection<int, array<string, mixed>> $academicChecks
     * @return Collection<int, array<string, mixed>>
     */
    private function workflows(Collection $personChecks, Collection $contactChecks, Collection $schoolChecks, Collection $academicChecks): Collection
    {
        $count = fn (Collection $checks, array $titles): int => (int) $checks
            ->whereIn('title', $titles)
            ->sum('count');

        return collect([
            [
                'title' => 'Emitir documentos oficiais',
                'description' => 'Pessoa, escola, matrícula e papel timbrado precisam estar completos antes de gerar PDFs oficiais.',
                'icon' => 'fa-file-signature',
                'count' => $count($personChecks, ['Pessoas ativas com cadastro incompleto'])
                    + $count($schoolChecks, ['Escolas com dados oficiais incompletos'])
                    + $count($academicChecks, ['Matrículas ativas com cadastro do estudante incompleto', 'Matrículas ativas sem curso associado']),
                'route' => route('data-quality.index', ['severity' => 'danger']),
            ],
            [
                'title' => 'Matricular estudante',
                'description' => 'Cadastros civis, responsáveis e cursos associados devem estar prontos para a matrícula fluir.',
                'icon' => 'fa-user-graduate',
                'count' => $count($personChecks, ['Pessoas sem CPF', 'Pessoas sem e-mail institucional'])
                    + $count($contactChecks, ['Estudantes menores sem responsável'])
                    + $count($academicChecks, ['Matrículas ativas sem curso associado']),
                'route' => route('enrollments.index'),
            ],
            [
                'title' => 'Fechar períodos e ano letivo',
                'description' => 'Períodos, critérios de aprovação, dias letivos e documentos finais precisam estar consistentes.',
                'icon' => 'fa-clipboard-check',
                'count' => $count($academicChecks, [
                    'Anos letivos ativos sem aprovação',
                    'Anos letivos ativos sem períodos avaliativos',
                    'Anos letivos sem critérios de aprovação',
                    'Anos letivos com menos dias letivos que o mínimo',
                ]),
                'route' => route('data-quality.index', ['severity' => 'danger']),
            ],
            [
                'title' => 'Liberar acesso ao sistema',
                'description' => 'Usuários ativos precisam ter e-mail institucional válido, CPF e vínculo ativo.',
                'icon' => 'fa-sign-in-alt',
                'count' => $count($personChecks, [
                    'Pessoas sem CPF',
                    'Pessoas sem e-mail institucional',
                    'E-mails institucionais fora do domínio',
                    'Pessoas ativas sem vínculo ativo',
                ]),
                'route' => route('people.index'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function issuedDocument(Request $request, array $data, ?School $school): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'data-quality-compliance-report',
            'person_id' => $request->user()->person_id,
            'school_id' => $school?->id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Relatório de conformidade documental e acadêmica',
                'summary' => $data['summary'],
                'school_id' => $school?->id,
                'school' => $school?->name,
                'severity' => $data['selectedSeverity'],
            ],
            'issued_at' => now(),
        ]);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * @return Collection<int, School>
     */
    private function availableSchools(Request $request): Collection
    {
        return School::query()
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Collection<int, School> $schools
     */
    private function selectedSchoolId(Request $request, Collection $schools): ?int
    {
        $schoolId = $request->integer('school_id');

        if ($schoolId < 1) {
            return null;
        }

        return $schools->contains('id', $schoolId) ? $schoolId : null;
    }

    /**
     * @param Collection<int, School> $schools
     * @return list<int>|null
     */
    private function schoolIdsForChecks(Request $request, Collection $schools, ?int $selectedSchoolId): ?array
    {
        if ($selectedSchoolId !== null) {
            return [$selectedSchoolId];
        }

        if ($request->user()->isAdministrator()) {
            return null;
        }

        return $schools->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function personChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->check(
                'Pessoas sem CPF',
                'Bloqueia login completo, emissão de documentos escolares e novos vínculos até a regularização.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('cpf')->orWhere('cpf', '')),
                'danger'
            ),
            $this->check(
                'Pessoas sem e-mail institucional',
                'Bloqueia acesso pelo Google Workspace até receberem um e-mail @ctjj.org.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('institutional_email')->orWhere('institutional_email', '')),
                'danger'
            ),
            $this->check(
                'Pessoas ativas com cadastro incompleto',
                'Bloqueia emissão de documentos oficiais. Complete dados civis, filiação, telefone e endereço.',
                $this->missingPersonDocumentDataScope($this->personScope(Person::query(), $schoolIds)),
                'danger'
            ),
            $this->check(
                'CPFs com formato suspeito',
                'Cadastros com CPF que não possui 11 dígitos numéricos após remover máscara.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereNotNull('cpf')
                    ->where('cpf', '!=', '')
                    ->whereRaw("length(replace(replace(replace(cpf, '.', ''), '-', ''), ' ', '')) != 11"),
                'warning'
            ),
            $this->check(
                'E-mails institucionais fora do domínio',
                'O login institucional deve usar o domínio ctjj.org.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereNotNull('institutional_email')
                    ->where('institutional_email', '!=', '')
                    ->where('institutional_email', 'not like', '%@ctjj.org'),
                'danger'
            ),
            $this->check(
                'Pessoas ativas sem vínculo ativo',
                'Pessoas ativas que não aparecem como estudante, docência, gestão, equipe ou administração ativa.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereDoesntHave('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles, $schoolIds)),
                'info'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function roleChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->roleCheck(
                'Vínculos sem data de início',
                'Todo vínculo escolar, exceto administração global, deve ter data de início definida.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', '!=', PersonSchoolRole::ROLE_ADMINISTRATOR)
                    ->whereNull('started_at'),
                'warning'
            ),
            $this->roleCheck(
                'Estudantes ativos sem escola',
                'Estudantes ativos precisam estar ligados a uma escola.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_STUDENT)
                    ->where('active', true)
                    ->whereNull('school_id'),
                'danger'
            ),
            $this->roleCheck(
                'Docência ativa sem escola',
                'Vínculos de docência ativos precisam estar ligados a uma escola.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_TEACHER)
                    ->where('active', true)
                    ->whereNull('school_id'),
                'danger'
            ),
            $this->roleCheck(
                'Gestão ativa sem função',
                'Vínculos de gestão precisam indicar Direção, Coordenação ou Secretaria.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_MANAGER)
                    ->where('active', true)
                    ->whereNull('position'),
                'warning'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function contactChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->check(
                'Estudantes menores sem responsável',
                'Bloqueia conferência documental: estudantes menores de 18 anos precisam ter ao menos um responsável legal cadastrado.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereNotNull('birth_date')
                    ->whereDate('birth_date', '>', now()->subYears(18)->toDateString())
                    ->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles, $schoolIds)->where('role', PersonSchoolRole::ROLE_STUDENT))
                    ->whereDoesntHave('contacts', fn (Builder $contacts) => $contacts->where('legal_guardian', true)),
                'danger'
            ),
            $this->contactCheck(
                'Responsáveis sem contato',
                'Contatos de responsáveis sem telefone e sem e-mail precisam ser complementados.',
                $this->contactScope(PersonContact::query(), $schoolIds)
                    ->where(function (Builder $query): void {
                        $query->where(fn (Builder $query) => $query->whereNull('phone')->orWhere('phone', ''))
                            ->where(fn (Builder $query) => $query->whereNull('secondary_phone')->orWhere('secondary_phone', ''))
                            ->where(fn (Builder $query) => $query->whereNull('email')->orWhere('email', ''));
                    }),
                'warning'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function schoolChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->schoolCheck(
                'Escolas com dados oficiais incompletos',
                'Bloqueia documentos oficiais com papel timbrado. Complete razão social, CNPJ, INEP, fundação, contatos, endereço e texto institucional.',
                $this->missingSchoolOfficialDataScope($this->schoolScope(School::query(), $schoolIds)),
                'danger'
            ),
            $this->schoolCheck(
                'Escolas sem CNPJ',
                'O CNPJ será necessário em relatórios e documentos oficiais.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('cnpj')->orWhere('cnpj', '')),
                'warning'
            ),
            $this->schoolCheck(
                'Escolas sem INEP',
                'O código INEP ajuda a identificar corretamente a unidade escolar.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('inep')->orWhere('inep', '')),
                'warning'
            ),
            $this->schoolCheck(
                'Escolas sem endereço completo',
                'Endereço, cidade e UF devem estar preenchidos para documentos emitidos pelo sistema.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(function (Builder $query): void {
                        $query->whereNull('address')
                            ->orWhere('address', '')
                            ->orWhereNull('city')
                            ->orWhere('city', '')
                            ->orWhereNull('state')
                            ->orWhere('state', '');
                    }),
                'info'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function academicChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->yearCheck(
                'Anos letivos ativos sem aprovação',
                'A aprovação formaliza o calendário. Sem ela, documentos finais e consolidações ficam em atenção.',
                $this->yearScope(AcademicYear::query(), $schoolIds)
                    ->where('active', true)
                    ->whereNull('approved_at'),
                'warning'
            ),
            $this->yearCheck(
                'Anos letivos ativos sem períodos avaliativos',
                'Sem períodos avaliativos não há fechamento de diários, comportamento, boletim ou ficha individual por período.',
                $this->yearScope(AcademicYear::query(), $schoolIds)
                    ->where('active', true)
                    ->whereDoesntHave('periods'),
                'danger'
            ),
            $this->yearCheck(
                'Anos letivos sem critérios de aprovação',
                'Informe a soma de pontos para aprovação e o percentual mínimo de frequência.',
                $this->yearScope(AcademicYear::query(), $schoolIds)
                    ->where(function (Builder $query): void {
                        $query->whereNull('passing_points')
                            ->orWhereNull('minimum_attendance_percentage');
                    }),
                'danger'
            ),
            $this->yearCheck(
                'Anos letivos com menos dias letivos que o mínimo',
                'Confira o calendário quando a contagem de dias letivos estiver abaixo do mínimo definido para o ano.',
                $this->yearScope(AcademicYear::query(), $schoolIds)
                    ->whereRaw('(select count(*) from calendar_days where calendar_days.academic_year_id = academic_years.id and calendar_days.counts_as_school_day = 1) < minimum_school_days'),
                'danger'
            ),
            $this->enrollmentCheck(
                'Matrículas ativas com cadastro do estudante incompleto',
                'Bloqueia documentos do estudante e exige regularização antes de novas emissões oficiais.',
                $this->enrollmentScope(StudentEnrollment::query(), $schoolIds)
                    ->where('status', StudentEnrollment::STATUS_ENROLLED)
                    ->whereHas('student', fn (Builder $student) => $this->missingPersonDocumentDataScope($student->where('active', true))),
                'danger'
            ),
            $this->enrollmentCheck(
                'Matrículas ativas sem curso associado',
                'A matrícula precisa estar ligada a pelo menos uma matriz/curso para boletim, ficha individual e histórico.',
                $this->enrollmentScope(StudentEnrollment::query(), $schoolIds)
                    ->where('status', StudentEnrollment::STATUS_ENROLLED)
                    ->whereDoesntHave('courses'),
                'danger'
            ),
        ]);
    }

    /**
     * @param Builder<Person> $query
     * @return array<string, mixed>
     */
    private function check(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('full_name')
                ->limit(8)
                ->get(['id', 'full_name', 'institutional_email', 'cpf']),
            'type' => 'people',
        ];
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @return array<string, mixed>
     */
    private function roleCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with(['person', 'school'])
                ->orderBy('role')
                ->limit(8)
                ->get(),
            'type' => 'roles',
        ];
    }

    /**
     * @param Builder<PersonContact> $query
     * @return array<string, mixed>
     */
    private function contactCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with('person')
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'type' => 'contacts',
        ];
    }

    /**
     * @param Builder<School> $query
     * @return array<string, mixed>
     */
    private function schoolCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'city', 'state']),
            'type' => 'schools',
        ];
    }

    /**
     * @param Builder<AcademicYear> $query
     * @return array<string, mixed>
     */
    private function yearCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with('school')
                ->orderByDesc('reference_year')
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'type' => 'years',
        ];
    }

    /**
     * @param Builder<StudentEnrollment> $query
     * @return array<string, mixed>
     */
    private function enrollmentCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with(['student', 'schoolClass.academicYear.school'])
                ->orderByDesc('enrolled_at')
                ->limit(8)
                ->get(),
            'type' => 'enrollments',
        ];
    }

    /**
     * @param Builder<Person> $query
     * @param list<int>|null $schoolIds
     * @return Builder<Person>
     */
    private function personScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->where('active', true)
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds));
            });
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonSchoolRole>
     */
    private function roleScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->whereHas('person', fn (Builder $person) => $person->where('active', true))
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds));
    }

    /**
     * @param Builder<PersonContact> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonContact>
     */
    private function contactScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->whereHas('person', fn (Builder $person) => $person->where('active', true))
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('person.schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds));
            });
    }

    /**
     * @param Builder<School> $query
     * @param list<int>|null $schoolIds
     * @return Builder<School>
     */
    private function schoolScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('id', $schoolIds));
    }

    /**
     * @param Builder<AcademicYear> $query
     * @param list<int>|null $schoolIds
     * @return Builder<AcademicYear>
     */
    private function yearScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds));
    }

    /**
     * @param Builder<StudentEnrollment> $query
     * @param list<int>|null $schoolIds
     * @return Builder<StudentEnrollment>
     */
    private function enrollmentScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
            $query->whereHas('schoolClass.academicYear', fn (Builder $year) => $year->whereIn('school_id', $schoolIds));
        });
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonSchoolRole>
     */
    private function activeRoleScope(Builder $query, ?array $schoolIds = null): Builder
    {
        return $this->roleScope($query, $schoolIds)
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }

    /**
     * @param Builder<Person> $query
     * @return Builder<Person>
     */
    private function missingPersonDocumentDataScope(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('full_name')
                ->orWhere('full_name', '')
                ->orWhereNull('cpf')
                ->orWhere('cpf', '')
                ->orWhereNull('birth_date')
                ->orWhereNull('birth_city')
                ->orWhere('birth_city', '')
                ->orWhereNull('birth_state')
                ->orWhere('birth_state', '')
                ->orWhereNull('nationality')
                ->orWhere('nationality', '')
                ->orWhereNull('mother_name')
                ->orWhere('mother_name', '')
                ->orWhereNull('institutional_email')
                ->orWhere('institutional_email', '')
                ->orWhereNull('phone')
                ->orWhere('phone', '');
        });
    }

    /**
     * @param Builder<School> $query
     * @return Builder<School>
     */
    private function missingSchoolOfficialDataScope(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('name')
                ->orWhere('name', '')
                ->orWhereNull('legal_name')
                ->orWhere('legal_name', '')
                ->orWhereNull('cnpj')
                ->orWhere('cnpj', '')
                ->orWhereNull('inep')
                ->orWhere('inep', '')
                ->orWhereNull('founded_at')
                ->orWhereNull('phone')
                ->orWhere('phone', '')
                ->orWhereNull('email')
                ->orWhere('email', '')
                ->orWhereNull('letterhead_text')
                ->orWhere('letterhead_text', '')
                ->orWhereNull('address')
                ->orWhere('address', '')
                ->orWhereNull('city')
                ->orWhere('city', '')
                ->orWhereNull('state')
                ->orWhere('state', '')
                ->orWhereNull('postal_code')
                ->orWhere('postal_code', '');
        });
    }
}
