<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\AcademicContextLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentIssuanceController extends Controller
{
    private const CURRENT_ENROLLMENT_DOCUMENTS = [
        'enrollment-declaration',
        'attendance-certificate',
        'enrollment-form',
    ];

    private const CURRENT_CLASS_DOCUMENTS = [
        'class-schedule',
    ];

    private const CURRENT_DIARY_DOCUMENTS = [
        'attendance-sheet',
    ];

    /**
     * @var array<string, array<string, bool|string>>
     */
    private const DOCUMENT_TYPES = [
        'enrollment-declaration' => [
            'group' => 'Estudante',
            'label' => 'Declaração de matrícula',
            'description' => 'Comprova a matrícula ativa do estudante.',
            'target' => 'enrollment',
            'icon' => 'fa-id-card',
        ],
        'schooling-declaration' => [
            'group' => 'Estudante',
            'label' => 'Declaração de escolaridade',
            'description' => 'Comprova vínculo escolar atual ou anterior.',
            'target' => 'enrollment',
            'icon' => 'fa-school',
        ],
        'completion-declaration' => [
            'group' => 'Estudante',
            'label' => 'Declaração de conclusão',
            'description' => 'Comprova uma conclusão com resultado aprovado.',
            'target' => 'enrollment',
            'icon' => 'fa-award',
        ],
        'attendance-certificate' => [
            'group' => 'Estudante',
            'label' => 'Atestado de frequência',
            'description' => 'Apresenta a frequência mensal, por período avaliativo ou anual, com todas as matrizes da matrícula.',
            'target' => 'enrollment',
            'icon' => 'fa-user-check',
            'attendance_scope' => true,
        ],
        'transfer-certificate' => [
            'group' => 'Estudante',
            'label' => 'Atestado de transferência',
            'description' => 'Disponível após o registro formal da transferência.',
            'target' => 'enrollment',
            'icon' => 'fa-exchange-alt',
        ],
        'enrollment-form' => [
            'group' => 'Estudante',
            'label' => 'Ficha de matrícula',
            'description' => 'Reúne dados da matrícula, do estudante e responsáveis.',
            'target' => 'enrollment',
            'icon' => 'fa-file-signature',
        ],
        'report-card' => [
            'group' => 'Estudante',
            'label' => 'Boletim escolar',
            'description' => 'Resultados, frequência e comportamento por período.',
            'target' => 'enrollment',
            'icon' => 'fa-chart-line',
            'score_view' => true,
        ],
        'individual-record' => [
            'group' => 'Estudante',
            'label' => 'Ficha individual',
            'description' => 'Documento acadêmico individual completo.',
            'target' => 'enrollment',
            'icon' => 'fa-file-alt',
            'score_view' => true,
        ],
        'academic-history' => [
            'group' => 'Estudante',
            'label' => 'Histórico escolar',
            'description' => 'Emite um histórico previamente cadastrado e conferido.',
            'target' => 'history',
            'icon' => 'fa-history',
        ],
        'class-schedule' => [
            'group' => 'Turma e diário',
            'label' => 'Horário da turma',
            'description' => 'Imprime o horário vigente ou cadastrado para a turma.',
            'target' => 'class',
            'icon' => 'fa-calendar-week',
        ],
        'class-report-cards' => [
            'group' => 'Turma e diário',
            'label' => 'Boletins da turma',
            'description' => 'Reúne em um único PDF o boletim de todas as matrículas ativas da turma.',
            'target' => 'class',
            'icon' => 'fa-file-pdf',
            'score_view' => true,
        ],
        'class-grade-mirror' => [
            'group' => 'Turma e diário',
            'label' => 'Espelho de notas da turma',
            'description' => 'Apresenta estudantes e resultados por período, com notas numéricas ou conceitos.',
            'target' => 'class',
            'icon' => 'fa-table',
            'score_view' => true,
        ],
        'class-final-results' => [
            'group' => 'Turma e diário',
            'label' => 'Ata de resultados finais da turma',
            'description' => 'Relação dos resultados finais das matrículas da turma.',
            'target' => 'class',
            'icon' => 'fa-poll',
        ],
        'teacher-diary' => [
            'group' => 'Turma e diário',
            'label' => 'Diário de classe',
            'description' => 'Diário consolidado de um componente curricular.',
            'target' => 'diary',
            'icon' => 'fa-book',
            'score_view' => true,
        ],
        'attendance-sheet' => [
            'group' => 'Turma e diário',
            'label' => 'Lista de chamada mensal',
            'description' => 'Folha mensal para chamada manual por componente.',
            'target' => 'diary',
            'icon' => 'fa-clipboard-list',
            'month' => true,
        ],
        'academic-calendar' => [
            'group' => 'Ano letivo',
            'label' => 'Calendário acadêmico',
            'description' => 'Calendário anual com períodos, siglas e dias letivos.',
            'target' => 'academic_year',
            'icon' => 'fa-calendar-alt',
        ],
        'academic-matrices' => [
            'group' => 'Ano letivo',
            'label' => 'Matrizes curriculares',
            'description' => 'Matrizes agrupadas por etapa do ano letivo.',
            'target' => 'academic_year',
            'icon' => 'fa-th-list',
        ],
        'academic-year-schedules' => [
            'group' => 'Ano letivo',
            'label' => 'Horários das turmas',
            'description' => 'Reúne os horários cadastrados nas turmas do ano letivo.',
            'target' => 'academic_year',
            'icon' => 'fa-clock',
        ],
        'academic-year-final-results' => [
            'group' => 'Ano letivo',
            'label' => 'Resultados finais do ano letivo',
            'description' => 'Reúne os resultados finais de todas as turmas.',
            'target' => 'academic_year',
            'icon' => 'fa-clipboard-check',
        ],
        'person-record' => [
            'group' => 'Cadastros',
            'label' => 'Ficha cadastral da pessoa',
            'description' => 'Dados pessoais, contatos e vínculos cadastrados.',
            'target' => 'person',
            'icon' => 'fa-address-card',
        ],
        'school-record' => [
            'group' => 'Cadastros',
            'label' => 'Ficha cadastral da escola',
            'description' => 'Dados institucionais e vínculos da unidade escolar.',
            'target' => 'school',
            'icon' => 'fa-building',
            'admin_only' => true,
        ],
    ];

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);
        $schoolIds = $this->accessibleSchoolIds($request->user());

        return view('document-issuance.index', [
            'documentTypes' => $this->availableTypes($request->user()),
            'schools' => School::query()->whereKey($schoolIds)->orderBy('name')->get(['id', 'name']),
            'academicYears' => AcademicYear::query()
                ->whereIn('school_id', $schoolIds)
                ->orderByDesc('reference_year')
                ->orderBy('name')
                ->get(['id', 'school_id', 'name', 'reference_year', 'starts_at', 'ends_at']),
            'academicPeriods' => AcademicPeriod::query()
                ->whereHas('academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
                ->orderBy('academic_year_id')
                ->orderBy('position')
                ->get(['id', 'academic_year_id', 'name', 'starts_at', 'ends_at']),
            'classes' => SchoolClass::query()
                ->whereHas('academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
                ->with(['academicYear:id,school_id,name,reference_year,starts_at,ends_at', 'courses:id,name,stage'])
                ->orderBy('name')
                ->get(['id', 'academic_year_id', 'name']),
        ]);
    }

    public function targets(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $types = $this->availableTypes($request->user());
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($types))],
            'q' => ['nullable', 'string', 'max:100'],
            'school_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
        ]);
        $schoolIds = $this->accessibleSchoolIds($request->user());
        $this->authorizeSchoolFilter($data['school_id'] ?? null, $schoolIds);
        $term = trim((string) ($data['q'] ?? ''));

        $targets = match ($types[$data['type']]['target']) {
            'enrollment' => $this->enrollmentTargets($request, $schoolIds, $term, $data['type']),
            'person' => $this->personTargets($request, $schoolIds, $term),
            'history' => $this->historyTargets($request, $schoolIds, $term),
            'class' => $this->classTargets($request, $schoolIds, $term, $data['type']),
            'academic_year' => $this->academicYearTargets($request, $schoolIds, $term),
            'school' => $this->schoolTargets($request, $schoolIds, $term),
            'diary' => $this->diaryTargets($request, $schoolIds, $term, $data['type']),
            default => collect(),
        };

        return response()->json(['targets' => $targets->values()]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $types = $this->availableTypes($request->user());
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($types))],
            'target_id' => ['required', 'integer'],
            'score_view' => ['nullable', Rule::in(['numeros', 'conceitos'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'attendance_scope' => ['nullable', Rule::in(['annual', 'period', 'month'])],
            'academic_period_id' => ['nullable', 'required_if:attendance_scope,period', 'integer'],
            'attendance_month' => ['nullable', 'required_if:attendance_scope,month', 'date_format:Y-m'],
        ]);
        $schoolIds = $this->accessibleSchoolIds($request->user());

        return match ($types[$data['type']]['target']) {
            'enrollment' => $this->issueEnrollment($data, $schoolIds),
            'person' => $this->issuePerson($data, $request->user(), $schoolIds),
            'history' => $this->issueHistory($data, $request->user(), $schoolIds),
            'class' => $this->issueClass($data, $schoolIds),
            'academic_year' => $this->issueAcademicYear($data, $schoolIds),
            'school' => $this->issueSchool($data, $request->user()),
            'diary' => $this->issueDiary($data, $schoolIds),
            default => abort(404),
        };
    }

    /**
     * @return array<string, array<string, bool|string>>
     */
    private function availableTypes(User $user): array
    {
        return collect(self::DOCUMENT_TYPES)
            ->reject(fn (array $type): bool => ($type['admin_only'] ?? false) && ! $user->isAdministrator())
            ->all();
    }

    /**
     * @return list<int>
     */
    private function accessibleSchoolIds(User $user): array
    {
        if ($user->isAdministrator()) {
            return School::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $user->manageableSchoolIds();
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->canManagePeople(), 403);
    }

    /**
     * @param  list<int>  $schoolIds
     */
    private function authorizeSchoolFilter(?int $schoolId, array $schoolIds): void
    {
        if ($schoolId !== null) {
            abort_unless(in_array($schoolId, $schoolIds, true), 403);
        }
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function enrollmentTargets(Request $request, array $schoolIds, string $term, string $type): Collection
    {
        return StudentEnrollment::query()
            ->select('student_enrollments.*')
            ->join('people', 'people.id', '=', 'student_enrollments.person_id')
            ->with([
                'student:id,full_name,social_name',
                'schoolClass.academicYear.school:id,name',
                'schoolClass.courses:id,name,stage',
                'schoolClass:id,academic_year_id,name',
                'courses:id,name,stage',
            ])
            ->whereHas('schoolClass.academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->when($this->requiresCurrentEnrollment($type), fn (Builder $query) => $this->scopeCurrentEnrollments($query))
            ->when($request->filled('school_id'), fn (Builder $query) => $query->whereHas('schoolClass.academicYear', fn (Builder $year) => $year->where('school_id', $request->integer('school_id'))))
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->whereHas('schoolClass', fn (Builder $class) => $class->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('class_id'), fn (Builder $query) => $query->where('school_class_id', $request->integer('class_id')))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('people.full_name', 'like', '%'.$term.'%')
                        ->orWhere('people.social_name', 'like', '%'.$term.'%')
                        ->orWhere('people.cpf', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('student_enrollments.enrolled_at')
            ->orderByDesc('student_enrollments.id')
            ->orderBy('people.full_name')
            ->limit(40)
            ->get()
            ->map(function (StudentEnrollment $enrollment) use ($type): array {
                $year = $enrollment->schoolClass?->academicYear;
                // Current-enrollment documents were already restricted by the
                // database query above. Do not classify the same record again
                // with a second, potentially divergent, presentation rule.
                [$enabled, $reason] = $this->requiresCurrentEnrollment($type)
                    ? [true, null]
                    : $this->enrollmentAvailability($enrollment, $type);

                return [
                    'id' => $enrollment->id,
                    'title' => $enrollment->student?->social_name ?: $enrollment->student?->full_name ?: 'Estudante sem nome',
                    'subtitle' => collect([
                        $year?->school?->name,
                        $year?->referenceYearsLabel(),
                        AcademicContextLabel::classWithStages($enrollment->schoolClass?->name, $enrollment->schoolClass?->courses ?? collect()),
                        $enrollment->statusLabel(),
                    ])->filter()->join(' · '),
                    'enabled' => $enabled,
                    'reason' => $reason,
                    'academic_year_id' => $year?->id,
                ];
            });
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function enrollmentAvailability(StudentEnrollment $enrollment, string $type): array
    {
        if ($this->requiresCurrentEnrollment($type) && ! $this->isCurrentEnrollment($enrollment)) {
            return [false, $this->currentEnrollmentUnavailableReason($enrollment)];
        }

        if ($type === 'enrollment-declaration' && ! $enrollment->isActive()) {
            return [false, 'A matrícula precisa estar ativa.'];
        }

        if ($type === 'completion-declaration' && $enrollment->final_result_status !== StudentEnrollment::FINAL_APPROVED) {
            return [false, 'O resultado final precisa estar aprovado.'];
        }

        if ($type === 'transfer-certificate' && ($enrollment->status !== StudentEnrollment::STATUS_TRANSFERRED || ! $enrollment->transferred_at)) {
            return [false, 'A transferência precisa estar registrada.'];
        }

        return [true, null];
    }

    private function currentEnrollmentUnavailableReason(StudentEnrollment $enrollment): string
    {
        $enrollment->loadMissing('schoolClass.academicYear');
        $class = $enrollment->schoolClass;
        $year = $class?->academicYear;

        return match (true) {
            ! $enrollment->isActive() => 'A matrícula não está ativa.',
            ! $class?->active => 'A turma desta matrícula está inativa.',
            ! in_array($enrollment->final_result_status, [null, StudentEnrollment::FINAL_PENDING], true) => 'A matrícula já possui resultado final. Use os documentos acadêmicos de arquivo.',
            ! $year?->active || $year?->isClosed() => 'O ano letivo está encerrado. Use ficha individual ou histórico escolar.',
            default => 'Esta matrícula não está disponível para este documento.',
        };
    }

    private function requiresCurrentEnrollment(string $type): bool
    {
        return in_array($type, self::CURRENT_ENROLLMENT_DOCUMENTS, true);
    }

    private function requiresCurrentClass(string $type): bool
    {
        return in_array($type, self::CURRENT_CLASS_DOCUMENTS, true);
    }

    private function requiresCurrentDiary(string $type): bool
    {
        return in_array($type, self::CURRENT_DIARY_DOCUMENTS, true);
    }

    private function scopeCurrentEnrollments(Builder $query): Builder
    {
        return $query
            ->where('student_enrollments.status', StudentEnrollment::STATUS_ENROLLED)
            ->where(function (Builder $enrollment): void {
                $enrollment
                    ->whereNull('student_enrollments.final_result_status')
                    ->orWhere('student_enrollments.final_result_status', StudentEnrollment::FINAL_PENDING);
            })
            ->whereHas('schoolClass', function (Builder $class): void {
                $class
                    ->where('school_classes.active', true)
                    ->where(function (Builder $context): void {
                        $context
                            ->whereHas('academicYear', fn (Builder $year) => $this->scopeOpenAcademicYear($year))
                            ->orWhereHas('courses', function (Builder $course): void {
                                $course
                                    ->where('academic_courses.stage', AcademicCourse::STAGE_TECHNICAL)
                                    ->where('academic_courses.active', true);
                            });
                    });
            });
    }

    private function scopeCurrentClasses(Builder $query): Builder
    {
        return $query
            ->where('school_classes.active', true)
            ->where(function (Builder $context): void {
                $context
                    ->whereHas('academicYear', fn (Builder $year) => $this->scopeOpenAcademicYear($year))
                    ->orWhere(function (Builder $technicalClass): void {
                        $technicalClass
                            ->whereHas('courses', function (Builder $course): void {
                                $course
                                    ->where('academic_courses.stage', AcademicCourse::STAGE_TECHNICAL)
                                    ->where('academic_courses.active', true);
                            })
                            ->whereHas('enrollments', function (Builder $enrollment): void {
                                $enrollment
                                    ->where('student_enrollments.status', StudentEnrollment::STATUS_ENROLLED)
                                    ->where(function (Builder $result): void {
                                        $result
                                            ->whereNull('student_enrollments.final_result_status')
                                            ->orWhere('student_enrollments.final_result_status', StudentEnrollment::FINAL_PENDING);
                                    });
                            });
                    });
            });
    }

    private function scopeOpenAcademicYear(Builder $query): Builder
    {
        return $query
            ->where('academic_years.active', true)
            ->whereNull('academic_years.closed_at');
    }

    private function isCurrentEnrollment(StudentEnrollment $enrollment): bool
    {
        $enrollment->loadMissing('schoolClass.academicYear', 'courses');

        if (! $enrollment->isActive() || ! $enrollment->schoolClass?->active) {
            return false;
        }

        if (! in_array($enrollment->final_result_status, [null, StudentEnrollment::FINAL_PENDING], true)) {
            return false;
        }

        $year = $enrollment->schoolClass->academicYear;
        $openYear = $year && $year->active && ! $year->isClosed();
        $ongoingTechnicalCourse = $enrollment->courses->contains(
            fn (AcademicCourse $course): bool => $course->active && $course->stage === AcademicCourse::STAGE_TECHNICAL
        );

        return $openYear || $ongoingTechnicalCourse;
    }

    private function isCurrentClass(SchoolClass $class): bool
    {
        $class->loadMissing('academicYear', 'courses', 'enrollments');

        if (! $class->active) {
            return false;
        }

        $year = $class->academicYear;
        $openYear = $year && $year->active && ! $year->isClosed();
        $ongoingTechnicalCourse = $class->courses->contains(
            fn (AcademicCourse $course): bool => $course->active && $course->stage === AcademicCourse::STAGE_TECHNICAL
        ) && $class->enrollments->contains(
            fn (StudentEnrollment $enrollment): bool => $enrollment->isActive()
                && in_array($enrollment->final_result_status, [null, StudentEnrollment::FINAL_PENDING], true)
        );

        return $openYear || $ongoingTechnicalCourse;
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function personTargets(Request $request, array $schoolIds, string $term): Collection
    {
        return Person::query()
            ->with('schoolRoles')
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds)))
            ->when($request->filled('school_id'), fn (Builder $query) => $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->where('school_id', $request->integer('school_id'))))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('full_name', 'like', '%'.$term.'%')
                        ->orWhere('social_name', 'like', '%'.$term.'%')
                        ->orWhere('institutional_email', 'like', '%'.$term.'%')
                        ->orWhere('cpf', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(40)
            ->get(['id', 'full_name', 'social_name', 'institutional_email', 'active'])
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'title' => $person->social_name ?: $person->full_name,
                'subtitle' => collect([$person->institutional_email, $person->hasActiveRoleForDate() ? 'Com vínculo ativo' : 'Sem vínculo ativo'])->filter()->join(' · '),
                'enabled' => true,
                'reason' => null,
            ]);
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function historyTargets(Request $request, array $schoolIds, string $term): Collection
    {
        return StudentAcademicHistory::query()
            ->select('student_academic_histories.*')
            ->join('people', 'people.id', '=', 'student_academic_histories.person_id')
            ->with(['student:id,full_name,social_name', 'school:id,name'])
            ->whereIn('school_id', $schoolIds)
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereHas('student.schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds)))
            ->when($request->filled('school_id'), fn (Builder $query) => $query->where('school_id', $request->integer('school_id')))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('people.full_name', 'like', '%'.$term.'%')
                        ->orWhere('people.social_name', 'like', '%'.$term.'%')
                        ->orWhere('student_academic_histories.title', 'like', '%'.$term.'%')
                        ->orWhere('student_academic_histories.stage', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('people.full_name')
            ->limit(40)
            ->get()
            ->map(fn (StudentAcademicHistory $history): array => [
                'id' => $history->id,
                'title' => $history->student?->social_name ?: $history->student?->full_name ?: 'Estudante sem nome',
                'subtitle' => collect([$history->title, $history->stage, $history->school?->name])->filter()->join(' · '),
                'enabled' => true,
                'reason' => null,
            ]);
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function classTargets(Request $request, array $schoolIds, string $term, string $type): Collection
    {
        return SchoolClass::query()
            ->with(['academicYear.school:id,name', 'courses:id,name,stage'])
            ->withCount('schedules')
            ->withCount([
                'enrollments as active_enrollments_count' => fn (Builder $query) => $query->where('status', StudentEnrollment::STATUS_ENROLLED),
            ])
            ->whereHas('academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->when($this->requiresCurrentClass($type), fn (Builder $query) => $this->scopeCurrentClasses($query))
            ->when($request->filled('school_id'), fn (Builder $query) => $query->whereHas('academicYear', fn (Builder $year) => $year->where('school_id', $request->integer('school_id'))))
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('class_id'), fn (Builder $query) => $query->whereKey($request->integer('class_id')))
            ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->map(function (SchoolClass $class) use ($type): array {
                $requiresEnrollments = in_array($type, ['class-report-cards', 'class-grade-mirror'], true);
                $hasEnrollments = (int) $class->active_enrollments_count > 0;
                $requiresCurrentClass = $this->requiresCurrentClass($type);
                $isCurrentClass = ! $requiresCurrentClass || $this->isCurrentClass($class);

                return [
                    'id' => $class->id,
                    'title' => AcademicContextLabel::classWithStages($class->name, $class->courses),
                    'subtitle' => collect([
                        $class->academicYear?->school?->name,
                        $class->academicYear?->referenceYearsLabel(),
                        (int) $class->active_enrollments_count.' '.((int) $class->active_enrollments_count === 1 ? 'matrícula ativa' : 'matrículas ativas'),
                        $class->active ? 'Turma ativa' : 'Turma inativa',
                    ])->filter()->join(' · '),
                    'enabled' => (! $requiresEnrollments || $hasEnrollments) && $isCurrentClass,
                    'reason' => match (true) {
                        $requiresCurrentClass && ! $isCurrentClass => 'Use documentos de arquivo para turmas de anos letivos encerrados.',
                        $requiresEnrollments && ! $hasEnrollments => 'A turma não possui matrículas ativas.',
                        default => null,
                    },
                ];
            });
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function academicYearTargets(Request $request, array $schoolIds, string $term): Collection
    {
        return AcademicYear::query()
            ->with('school:id,name')
            ->whereIn('school_id', $schoolIds)
            ->when($request->filled('school_id'), fn (Builder $query) => $query->where('school_id', $request->integer('school_id')))
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->whereKey($request->integer('academic_year_id')))
            ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $search) use ($term): void {
                $search->where('name', 'like', '%'.$term.'%')->orWhere('reference_year', 'like', '%'.$term.'%');
            }))
            ->orderByDesc('reference_year')
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'title' => $year->referenceYearsLabel(),
                'subtitle' => collect([$year->school?->name, 'Calendário: '.$year->name, $year->active ? 'Ano letivo ativo' : 'Ano letivo inativo'])->filter()->join(' · '),
                'enabled' => true,
                'reason' => null,
            ]);
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function schoolTargets(Request $request, array $schoolIds, string $term): Collection
    {
        return School::query()
            ->whereKey($schoolIds)
            ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'city', 'state', 'active'])
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'title' => $school->name,
                'subtitle' => collect([$school->city, $school->state, $school->active ? 'Escola ativa' : 'Escola inativa'])->filter()->join(' · '),
                'enabled' => true,
                'reason' => null,
            ]);
    }

    /**
     * @param  list<int>  $schoolIds
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function diaryTargets(Request $request, array $schoolIds, string $term, string $type): Collection
    {
        return SchoolClassComponent::query()
            ->with([
                'schoolClass.academicYear.school:id,name',
                'component.course:id,name,stage,academic_year_id',
                'teacher:id,full_name',
            ])
            ->whereHas('schoolClass.academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->when($this->requiresCurrentDiary($type), function (Builder $query): void {
                $query
                    ->where('school_class_components.active', true)
                    ->whereHas('schoolClass', fn (Builder $class) => $this->scopeCurrentClasses($class));
            })
            ->when($request->filled('school_id'), fn (Builder $query) => $query->whereHas('schoolClass.academicYear', fn (Builder $year) => $year->where('school_id', $request->integer('school_id'))))
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->whereHas('schoolClass', fn (Builder $class) => $class->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('class_id'), fn (Builder $query) => $query->where('school_class_id', $request->integer('class_id')))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->whereHas('component', fn (Builder $component) => $component->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('schoolClass', fn (Builder $class) => $class->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('teacher', fn (Builder $teacher) => $teacher->where('full_name', 'like', '%'.$term.'%'));
                });
            })
            ->limit(40)
            ->get()
            ->sortBy(fn (SchoolClassComponent $assignment): string => ($assignment->schoolClass?->name ?? '').' '.($assignment->component?->name ?? ''))
            ->values()
            ->map(function (SchoolClassComponent $assignment): array {
                $year = $assignment->schoolClass?->academicYear;

                return [
                    'id' => $assignment->id,
                    'title' => ($assignment->component?->name ?? 'Componente não informado').' · '
                        .AcademicContextLabel::classWithStages(
                            $assignment->schoolClass?->name,
                            collect([$assignment->component?->course])->filter(),
                        ),
                    'subtitle' => collect([
                        $year?->school?->name,
                        $year?->referenceYearsLabel(),
                        $assignment->component?->course?->name,
                        $assignment->teacher?->full_name ? 'Docência: '.$assignment->teacher->full_name : 'Docência não definida',
                    ])->filter()->join(' · '),
                    'enabled' => true,
                    'reason' => null,
                ];
            });
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issueEnrollment(array $data, array $schoolIds): RedirectResponse
    {
        $enrollment = StudentEnrollment::query()
            ->whereKey($data['target_id'])
            ->whereHas('schoolClass.academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->firstOrFail();
        $enrollment->loadMissing('schoolClass.academicYear', 'courses');
        [$enabled, $reason] = $this->enrollmentAvailability($enrollment, $data['type']);

        if (! $enabled) {
            throw ValidationException::withMessages([
                'target_id' => $reason ?? 'Este documento nÃ£o estÃ¡ disponÃ­vel para esta matrÃ­cula.',
            ]);
        }

        $route = match ($data['type']) {
            'enrollment-declaration' => 'enrollments.enrollment-declaration.pdf',
            'schooling-declaration' => 'enrollments.schooling-declaration.pdf',
            'completion-declaration' => 'enrollments.completion-declaration.pdf',
            'attendance-certificate' => 'enrollments.attendance-certificate.pdf',
            'transfer-certificate' => 'enrollments.transfer-certificate.pdf',
            'enrollment-form' => 'enrollments.pdf',
            'report-card' => 'enrollments.report-card.pdf',
            'individual-record' => 'enrollments.individual-record.pdf',
            default => abort(404),
        };
        $parameters = ['enrollment' => $enrollment];

        if (in_array($data['type'], ['report-card', 'individual-record'], true)) {
            $parameters['notas'] = $data['score_view'] ?? 'numeros';
        }

        if ($data['type'] === 'attendance-certificate') {
            $parameters['attendance_scope'] = $data['attendance_scope'] ?? 'annual';

            if ($parameters['attendance_scope'] === 'period') {
                $parameters['academic_period_id'] = $data['academic_period_id'] ?? null;
            }

            if ($parameters['attendance_scope'] === 'month') {
                $parameters['attendance_month'] = $data['attendance_month'] ?? null;
            }
        }

        return redirect()->route($route, $parameters);
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issuePerson(array $data, User $user, array $schoolIds): RedirectResponse
    {
        $person = Person::query()
            ->whereKey($data['target_id'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds)))
            ->firstOrFail();

        return redirect()->route('people.pdf', $person);
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issueHistory(array $data, User $user, array $schoolIds): RedirectResponse
    {
        $history = StudentAcademicHistory::query()
            ->with('student')
            ->whereKey($data['target_id'])
            ->whereIn('school_id', $schoolIds)
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereHas('student.schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds)))
            ->firstOrFail();

        return redirect()->route('people.histories.pdf', [$history->student, $history]);
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issueClass(array $data, array $schoolIds): RedirectResponse
    {
        $class = SchoolClass::query()
            ->with(['academicYear', 'courses', 'enrollments'])
            ->whereKey($data['target_id'])
            ->whereHas('academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->firstOrFail();

        if ($this->requiresCurrentClass($data['type']) && ! $this->isCurrentClass($class)) {
            throw ValidationException::withMessages([
                'target_id' => 'Use documentos de arquivo para turmas de anos letivos encerrados.',
            ]);
        }

        if (in_array($data['type'], ['class-report-cards', 'class-grade-mirror'], true)
            && ! $class->enrollments->contains(fn (StudentEnrollment $enrollment): bool => $enrollment->isActive())) {
            throw ValidationException::withMessages([
                'target_id' => 'A turma nÃ£o possui matrÃ­culas ativas.',
            ]);
        }

        return match ($data['type']) {
            'class-schedule' => redirect()->route('academic-years.classes.schedules.pdf', [$class->academicYear, $class]),
            'class-report-cards' => redirect()->route('classes.report-cards.pdf', [
                'class' => $class,
                'notas' => $data['score_view'] ?? 'conceitos',
            ]),
            'class-grade-mirror' => redirect()->route('classes.grade-mirror.pdf', [
                'class' => $class,
                'notas' => $data['score_view'] ?? 'conceitos',
            ]),
            'class-final-results' => redirect()->route('classes.final-results.pdf', $class),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issueAcademicYear(array $data, array $schoolIds): RedirectResponse
    {
        $year = AcademicYear::query()->whereKey($data['target_id'])->whereIn('school_id', $schoolIds)->firstOrFail();
        $route = match ($data['type']) {
            'academic-calendar' => 'academic-years.calendar-pdf',
            'academic-matrices' => 'academic-years.matrices-pdf',
            'academic-year-schedules' => 'academic-years.schedules-pdf',
            'academic-year-final-results' => 'academic-years.final-results.pdf',
            default => abort(404),
        };

        return redirect()->route($route, $year);
    }

    /** @param array<string, mixed> $data */
    private function issueSchool(array $data, User $user): RedirectResponse
    {
        abort_unless($user->isAdministrator(), 403);

        return redirect()->route('schools.pdf', School::query()->findOrFail($data['target_id']));
    }

    /** @param array<string, mixed> $data @param list<int> $schoolIds */
    private function issueDiary(array $data, array $schoolIds): RedirectResponse
    {
        $assignment = SchoolClassComponent::query()
            ->with(['schoolClass.academicYear', 'schoolClass.courses', 'schoolClass.enrollments', 'component'])
            ->whereKey($data['target_id'])
            ->whereHas('schoolClass.academicYear', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->firstOrFail();

        if ($this->requiresCurrentDiary($data['type'])
            && (! $assignment->active || ! $this->isCurrentClass($assignment->schoolClass))) {
            throw ValidationException::withMessages([
                'target_id' => 'A lista de chamada manual sÃ³ fica disponÃ­vel para turmas em andamento.',
            ]);
        }

        $parameters = [
            'schoolClass' => $assignment->schoolClass,
            'component' => $assignment->component,
        ];

        if ($data['type'] === 'attendance-sheet') {
            $parameters['month'] = $data['month'] ?? now()->format('Y-m');

            return redirect()->route('teacher-diaries.attendance-sheet.pdf', $parameters);
        }

        abort_unless($data['type'] === 'teacher-diary', 404);
        $parameters['notas'] = $data['score_view'] ?? 'numeros';

        return redirect()->route('teacher-diaries.pdf', $parameters);
    }
}
