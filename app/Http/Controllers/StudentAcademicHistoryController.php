<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use App\Support\BrazilianStates;
use App\Support\CurriculumAreaStage;
use App\Support\HistoryCurriculumCatalog;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentAcademicHistoryCompleteness;
use App\Support\StudentHistoryConsolidator;
use App\Support\StudentHistoryEditGuard;
use App\Support\UnifiedStudentHistorySynchronizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentAcademicHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $isAdministrator = $request->user()->isAdministrator();
        $schools = $isAdministrator ? School::query()->where('active', true)->orderBy('name')->get() : collect();
        $selectedSchoolId = $isAdministrator && $request->filled('school') ? $request->integer('school') : null;
        abort_if($selectedSchoolId && ! $schools->contains('id', $selectedSchoolId), 404);
        $schoolIds = $isAdministrator
            ? ($selectedSchoolId ? [$selectedSchoolId] : $schools->pluck('id')->all())
            : $request->user()->manageableSchoolIds();

        $students = Person::query()
            ->whereHas('studentEnrollments.schoolClass.academicYear', fn ($query) => $query->whereIn('school_id', $schoolIds))
            ->when($request->filled('q'), fn ($query) => $query->where('full_name', 'like', '%'.trim((string) $request->input('q')).'%'))
            ->withCount(['studentEnrollments' => fn ($query) => $query->whereHas('schoolClass.academicYear', fn ($academicYearQuery) => $academicYearQuery->whereIn('school_id', $schoolIds))])
            ->with(['academicHistories' => fn ($query) => $query->where('is_unified', true)->withCount('years')])
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        return view('student-histories.index', compact('students', 'schools', 'selectedSchoolId', 'isAdministrator'));
    }

    public function student(Request $request, Person $person, StudentAcademicHistoryCompleteness $completeness): View
    {
        $this->authorizeEnrolledStudent($request, $person);

        $this->synchronizeExistingHistories($request, $person);

        $visibleSchoolIds = $request->user()->manageableSchoolIds();
        $person->load(['academicHistories' => fn ($query) => $query
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->where(function ($query) use ($visibleSchoolIds): void {
                $query->whereNull('school_id')->orWhereIn('school_id', $visibleSchoolIds);
            }))
            ->with('years.records.component')->withCount(['years', 'components'])]);
        $unifiedHistories = $person->academicHistories->where('is_unified', true);

        return view('student-histories.student', [
            'person' => $person,
            'historyCompleteness' => $unifiedHistories->mapWithKeys(fn ($history) => [$history->id => $completeness->evaluate($history)]),
            'separateHistories' => $person->academicHistories->where('is_unified', false)->filter(fn ($history) => $history->years->isNotEmpty()),
            'availableHistoryStages' => $this->availableHistoryStages($request, $person),
        ]);
    }

    public function unified(Request $request, Person $person, string $stage, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeEnrolledStudent($request, $person);
        abort_unless(in_array($stage, [
            AcademicCourse::STAGE_ELEMENTARY,
            AcademicCourse::STAGE_HIGH_SCHOOL,
            AcademicCourse::STAGE_TECHNICAL,
        ], true), 404);
        if (! in_array($stage, $this->availableHistoryStages($request, $person), true)) {
            throw ValidationException::withMessages([
                'stage' => __('O histórico só pode ser iniciado para uma etapa em que o estudante possua matrícula registrada.'),
            ]);
        }
        $schoolId = $person->studentEnrollments()
            ->whereHas('courses', fn ($query) => $query->where('stage', $stage))
            ->whereHas('schoolClass.academicYear', fn ($query) => $query
                ->whereIn('school_id', $request->user()->isAdministrator() ? School::query()->pluck('id') : $request->user()->manageableSchoolIds()))
            ->with('schoolClass.academicYear')
            ->latest('enrolled_at')
            ->first()
            ?->schoolClass->academicYear->school_id;
        $schoolId ??= $person->academicHistories()->where('is_unified', true)->where('education_stage', $stage)
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->whereIn('school_id', $request->user()->manageableSchoolIds()))
            ->value('school_id');
        abort_unless($schoolId, 404);
        $history = $synchronizer->synchronize($person, $schoolId, $request->user()->person_id, $stage);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Histórico unificado atualizado com os dados disponíveis no sistema. Os lançamentos manuais foram preservados.'));
    }

    public function importYear(Request $request, Person $person, string $stage, StudentAcademicHistoryYear $year, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeEnrolledStudent($request, $person);
        $source = $year->history;
        abort_unless($source && $source->person_id === $person->id, 404);
        $this->authorizeHistory($request, $person, $source);
        abort_unless(in_array($stage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL, AcademicCourse::STAGE_TECHNICAL], true)
            && $year->stage === AcademicCourse::STAGE_LABELS[$stage], 422);
        $history = $synchronizer->synchronize($person, $source->school_id, $request->user()->person_id, $stage);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Lançamentos incorporados ao histórico único da etapa.'));
    }

    public function editDetails(Request $request, Person $person, StudentAcademicHistory $history): View|RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        if (! $history->is_unified && ($canonical = $this->canonicalHistory($request, $person, $history, app(UnifiedStudentHistorySynchronizer::class)))) {
            return redirect()->route('people.histories.details.edit', [$person, $canonical]);
        }

        return view('student-histories.edit-details', [
            'person' => $person,
            'history' => $history,
            'schools' => $this->schools($request),
        ]);
    }

    public function updateDetails(Request $request, Person $person, StudentAcademicHistory $history): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        if (! $history->is_unified && ! $history->active) {
            throw ValidationException::withMessages(['history' => __('Este documento foi incorporado ao histórico da etapa. Edite os dados no documento único.')]);
        }
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'issued_place' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ]);
        abort_unless($request->user()->canManageSchool((int) $data['school_id']), 403);
        $history->update(array_merge($data, [
            'active' => $request->boolean('active'),
            'updated_by_person_id' => $request->user()->person_id,
        ]));

        return redirect()->route('people.histories.show', [$person, $history])->with('status', __('Dados gerais do histórico atualizados.'));
    }

    public function create(Request $request, Person $person): View
    {
        $this->authorizePerson($request, $person);

        return view('student-histories.create', [
            'person' => $person,
            'history' => new StudentAcademicHistory,
            'schools' => $this->schools($request),
            'states' => $this->states(),
        ]);
    }

    public function store(Request $request, Person $person, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizePerson($request, $person);
        $data = $this->validatedData($request, creating: true);
        $histories = DB::transaction(function () use ($request, $person, $data, $synchronizer) {
            $histories = collect();
            foreach (collect($data['years'])->groupBy('stage', preserveKeys: true) as $label => $years) {
                $stage = array_search($label, AcademicCourse::STAGE_LABELS, true);
                if (! $stage || $stage === AcademicCourse::STAGE_OTHER) {
                    throw ValidationException::withMessages(['years' => __('Identifique a etapa escolar de cada ano antes de salvar.')]);
                }
                $history = $synchronizer->synchronize($person, $data['history']['school_id'], $request->user()->person_id, $stage);
                foreach ($years as $index => $yearData) {
                    unset($yearData['id']);
                    $year = $history->years()->create(array_merge($yearData, [
                        'position' => $history->years()->count() + 1,
                        'source' => 'external', 'student_enrollment_id' => null,
                    ]));
                    foreach ($data['components'] as $componentData) {
                        $record = $componentData['records'][$index] ?? [];
                        $registered = (bool) ($record['registered'] ?? false);
                        unset($record['registered']);
                        if (! $registered && collect($record)->filter(fn ($value) => filled($value))->isEmpty()) {
                            continue;
                        }
                        unset($componentData['records']);
                        $component = $history->components()->firstOrCreate($componentData, ['position' => $history->components()->count() + 1]);
                        $component->records()->create($record + ['student_academic_history_year_id' => $year->id]);
                    }
                }
                $histories->push($history);
            }

            return $histories;
        });

        return $histories->count() === 1
            ? redirect()->route('people.histories.show', [$person, $histories->first()])->with('status', __('Anos incorporados ao histórico único da etapa.'))
            : redirect()->route('student-histories.student', $person)->with('status', __('Anos incorporados aos históricos das etapas correspondentes.'));
    }

    public function show(Request $request, Person $person, StudentAcademicHistory $history, StudentAcademicHistoryCompleteness $completeness, UnifiedStudentHistorySynchronizer $synchronizer): View|RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        if (! $history->is_unified) {
            if ($canonical = $this->canonicalHistory($request, $person, $history, $synchronizer)) {
                return redirect()->route('people.histories.show', [$person, $canonical]);
            }
        }

        if ($history->is_unified && $history->education_stage) {
            $history = $synchronizer->synchronize(
                $person,
                $history->school_id,
                $request->user()->person_id,
                $history->education_stage,
            );
        }

        $history->load(['school', 'years.records', 'components.records.year']);

        return view('student-histories.show', [
            'person' => $person,
            'history' => $history,
            'historyCompleteness' => $completeness->evaluate($history),
            'technicalPeriodDurations' => $this->technicalPeriodDurations($history),
        ]);
    }

    public function edit(Request $request, Person $person, StudentAcademicHistory $history, UnifiedStudentHistorySynchronizer $synchronizer): View|RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        if (! $history->is_unified) {
            if ($canonical = $this->canonicalHistory($request, $person, $history, $synchronizer)) {
                return redirect()->route('people.histories.edit', [$person, $canonical]);
            }
        }

        if ($history->is_unified && $history->education_stage) {
            $history = $synchronizer->synchronize(
                $person,
                $history->school_id,
                $request->user()->person_id,
                $history->education_stage,
            );
        }

        return view('student-histories.edit', [
            'person' => $person,
            'history' => $history->load(['school', 'years.records.component', 'components.records.year']),
            'historyVersion' => app(StudentHistoryEditGuard::class)->version($history),
            'schools' => $this->schools($request),
            'states' => $this->states(),
        ]);
    }

    public function update(Request $request, Person $person, StudentAcademicHistory $history, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);

        if (! $history->is_unified && ! $history->active) {
            throw ValidationException::withMessages(['history' => __('Este documento foi incorporado ao histórico da etapa. Reabra o cadastro para editar o documento único.')]);
        }

        DB::transaction(function () use ($history, $request, $person, $synchronizer): void {
            StudentAcademicHistory::query()->whereKey($history->id)->lockForUpdate()->firstOrFail();
            $history->refresh();
            $guard = app(StudentHistoryEditGuard::class);
            $guard->assertCurrent($history, $request->input('history_version'));
            $request->validate(['history_payload_complete' => ['required', Rule::in(['1'])]], [
                'history_payload_complete.required' => __('O formulário chegou incompleto. Nenhum dado foi salvo. Reabra o histórico e tente novamente.'),
                'history_payload_complete.in' => __('O formulário chegou incompleto. Nenhum dado foi salvo. Reabra o histórico e tente novamente.'),
            ]);
            $data = $this->validatedData($request);
            $guard->assertYearsRetained($history, $data['years'], $data['removed_year_ids']);
            $history->update($data['history'] + [
                'updated_by_person_id' => $request->user()->person_id,
            ]);
            if ($history->is_unified) {
                $data['years'] = collect($data['years'])->map(function (array $year): array {
                    $year['source'] = ($year['source'] ?? null) === 'external' ? 'external' : 'manual';
                    $year['student_enrollment_id'] = null;

                    return $year;
                })->all();

            }

            $this->syncRows($history, $data);

            if ($history->is_unified && $history->education_stage) {
                $synchronizer->synchronize(
                    $person,
                    $history->school_id,
                    $request->user()->person_id,
                    $history->education_stage,
                );
            }

        });

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Histórico escolar atualizado com sucesso.'));
    }

    public function destroy(Request $request, Person $person, StudentAcademicHistory $history): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);

        $history->delete();

        return redirect()->route('people.student-map.show', $person)
            ->with('status', __('Histórico escolar removido.'));
    }

    public function destroyYear(Request $request, Person $person, StudentAcademicHistory $history, StudentAcademicHistoryYear $year): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        abort_unless($year->student_academic_history_id === $history->id && $year->source !== 'system', 404);

        DB::transaction(function () use ($history, $year): void {
            StudentAcademicHistory::query()->whereKey($history->id)->lockForUpdate()->firstOrFail();
            $history->years()->findOrFail($year->id)->delete();
            $history->components()->whereDoesntHave('records')->delete();
            if (! $history->is_unified && ! $history->years()->exists()) {
                $history->delete();
            }
        });

        return redirect()->route('student-histories.student', $person)
            ->with('status', __('Ano cadastrado removido do histórico.'));
    }

    public function pdf(Request $request, Person $person, StudentAcademicHistory $history, UnifiedStudentHistorySynchronizer $synchronizer, StudentAcademicHistoryCompleteness $completeness): Response|RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
        if (! $history->is_unified) {
            if ($canonical = $this->canonicalHistory($request, $person, $history, $synchronizer)) {
                return redirect()->route('people.histories.pdf', [$person, $canonical]);
            }

            return redirect()->route('student-histories.student', $person)
                ->with('status', __('Identifique a etapa dos lançamentos antes de emitir o histórico.'));
        }

        if ($history->is_unified && $history->education_stage) {
            $history = $synchronizer->synchronize(
                $person,
                $history->school_id,
                $request->user()->person_id,
                $history->education_stage,
            );
        }

        $history->load(['school', 'student', 'years.records', 'components.records.year']);

        $historyCompleteness = $completeness->evaluate($history);
        if (! $historyCompleteness['complete']) {
            return redirect()->route('people.histories.show', [$person, $history])->with('status', $historyCompleteness['message']);
        }

        if ($message = OfficialDocumentCompliance::studentMessage($person, $request->boolean('confirm_missing_student_cpf'))) {
            return redirect()->route('people.histories.show', [$person, $history])->with('status', $message);
        }

        if (! $history->school) {
            return redirect()->route('people.histories.edit', [$person, $history])
                ->with('status', __('Não é possível emitir histórico escolar sem escola relacionada ao documento.'));
        }

        if ($message = OfficialDocumentCompliance::schoolMessage($history->school)) {
            return redirect()->route('schools.edit', $history->school)->with('status', $message);
        }

        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'student-academic-history',
            'person_id' => $person->id,
            'school_id' => $history->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $history->title.' - '.$person->full_name,
                'student_academic_history_id' => $history->id,
                'rows_count' => $history->components->count(),
            ],
            'issued_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.student-academic-history', [
            'person' => $person,
            'history' => $history,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($history->school),
            'technicalPeriodDurations' => $this->technicalPeriodDurations($history),
        ])->setPaper('a4', 'portrait');

        $pdf->render();
        $pdf->addInfo([
            'Title' => $history->title.' - '.$person->full_name.' - '.$issuedDocument->verification_code,
            'Subject' => 'Histórico escolar emitido pelo Beabá',
            'Author' => $issuedDocument->person?->full_name ?? 'Beabá',
        ]);

        return $pdf->stream('beaba-historico-escolar-'.$person->id.'-'.$history->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function technicalPeriodDurations(StudentAcademicHistory $history)
    {
        if ($history->education_stage !== AcademicCourse::STAGE_TECHNICAL) {
            return collect();
        }

        return $history->student->studentEnrollments()
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('school_id', $history->school_id))
            ->with(['courses.startsPeriod', 'courses.endsPeriod', 'schoolClass.academicYear.periods'])
            ->get()
            ->flatMap(function (StudentEnrollment $enrollment) {
                $technicalCourses = $enrollment->courses->where('stage', AcademicCourse::STAGE_TECHNICAL);
                $periods = $enrollment->schoolClass?->academicYear?->periods ?? collect();

                return $technicalCourses->flatMap(function (AcademicCourse $course) use ($periods) {
                    $firstPosition = $course->startsPeriod?->position ?? $periods->min('position');
                    $lastPosition = $course->endsPeriod?->position ?? $periods->max('position');

                    return $periods->whereBetween('position', [$firstPosition, $lastPosition]);
                });
            })
            ->unique('id')
            ->sortBy([['starts_at', 'asc'], ['position', 'asc']])
            ->values();
    }

    private function canonicalHistory(Request $request, Person $person, StudentAcademicHistory $source, UnifiedStudentHistorySynchronizer $synchronizer): ?StudentAcademicHistory
    {
        $stage = app(StudentHistoryConsolidator::class)->stage($source);
        if (! $stage || ! $source->school_id || $stage === AcademicCourse::STAGE_OTHER) {
            return null;
        }

        return $synchronizer->synchronize($person, $source->school_id, $request->user()->person_id, $stage);
    }

    private function synchronizeExistingHistories(Request $request, Person $person): void
    {
        app(UnifiedStudentHistorySynchronizer::class)->synchronizeExisting(
            $person, $request->user()->person_id,
            $request->user()->isAdministrator() ? null : $request->user()->manageableSchoolIds(),
        );
    }

    private function authorizePerson(Request $request, Person $person): void
    {
        abort_unless($request->user()->canManagePeople(), 403);

        if (! $request->user()->isAdministrator()) {
            abort_unless($person->schoolRoles()->whereIn('school_id', $request->user()->manageableSchoolIds())->exists(), 403);
        }
    }

    private function authorizeEnrolledStudent(Request $request, Person $person): void
    {
        $this->authorizePerson($request, $person);
        $schoolIds = $request->user()->isAdministrator() ? School::query()->pluck('id')->all() : $request->user()->manageableSchoolIds();
        abort_unless($person->studentEnrollments()->whereHas('schoolClass.academicYear', fn ($query) => $query->whereIn('school_id', $schoolIds))->exists()
            || $person->academicHistories()->whereIn('school_id', $schoolIds)->exists(), 404);
    }

    /**
     * @return array<int, string>
     */
    private function availableHistoryStages(Request $request, Person $person): array
    {
        $schoolIds = $request->user()->isAdministrator()
            ? School::query()->pluck('id')->all()
            : $request->user()->manageableSchoolIds();

        $stages = $person->studentEnrollments()
            ->whereHas('schoolClass.academicYear', fn ($query) => $query
                ->whereIn('school_id', $schoolIds))
            ->with('courses:id,stage')
            ->get()
            ->flatMap->courses
            ->pluck('stage')
            ->filter(fn (string $stage): bool => in_array($stage, [
                AcademicCourse::STAGE_ELEMENTARY,
                AcademicCourse::STAGE_HIGH_SCHOOL,
                AcademicCourse::STAGE_TECHNICAL,
            ], true))
            ->unique()
            ->values()
            ->all();

        return collect($stages)->merge($person->academicHistories()
            ->where('is_unified', true)->whereIn('school_id', $schoolIds)->pluck('education_stage'))
            ->filter()->unique()->values()->all();
    }

    private function authorizeHistory(Request $request, Person $person, StudentAcademicHistory $history): void
    {
        abort_unless($history->person_id === $person->id, 404);
        $this->authorizePerson($request, $person);

        if (! $request->user()->isAdministrator() && $history->school_id) {
            abort_unless(in_array($history->school_id, $request->user()->manageableSchoolIds(), true), 403);
        }
    }

    /**
     * @return array<int, School>
     */
    private function schools(Request $request)
    {
        return $request->user()->isAdministrator()
            ? School::query()->where('active', true)->orderBy('name')->get()
            : School::query()->whereIn('id', $request->user()->manageableSchoolIds())->orderBy('name')->get();
    }

    /**
     * @return array<string, string>
     */
    private function states(): array
    {
        return array_combine(BrazilianStates::codes(), BrazilianStates::codes());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $creating = false): array
    {
        if ($request->boolean('formation_totals_present')) {
            $this->prepareGlobalYears($request);
        }
        if (! $request->boolean('year_components_present')) {
            return $this->validatedMatrixData($request, $creating);
        }
        $request->validate([
            'years' => ['required', 'array', 'min:1'],
            'year_components' => ['nullable', 'array'],
            'year_components.*' => ['array'],
            'year_components.*.*' => ['array'],
            'year_components.*.*.record' => ['nullable', 'array'],
        ]);
        $components = [];
        $origins = [];
        foreach ($request->input('year_components', []) as $yearIndex => $rows) {
            if (! ctype_digit((string) $yearIndex) || ! array_key_exists($yearIndex, $request->input('years'))) {
                throw ValidationException::withMessages(['year_components' => __('O componente deve pertencer a um ano deste formulário.')]);
            }
            foreach ($rows as $rowIndex => $row) {
                $origins[count($components)] = "year_components.{$yearIndex}.{$rowIndex}";
                $record = $row['record'] ?? [];
                $components[] = [
                    'formation' => $row['formation'] ?? null,
                    'knowledge_area' => $row['knowledge_area'] ?? null,
                    'name' => $row['name'] ?? null,
                    'module_label' => $row['module_label'] ?? null,
                    'regulatory_reference' => $row['regulatory_reference'] ?? null,
                    'records' => [$yearIndex => array_merge($record, ['registered' => true])],
                ];
            }
        }
        $request->merge(['components' => $components]);
        try {
            return $this->validatedMatrixData($request, $creating);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                if (preg_match('/^components\.(\d+)\.(.*)$/', $field, $match) && isset($origins[(int) $match[1]])) {
                    $field = $origins[(int) $match[1]].'.'.preg_replace('/^records\.\d+\./', 'record.', $match[2]);
                }
                $errors[$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    private function prepareGlobalYears(Request $request): void
    {
        $request->validate(['years' => ['required', 'array', 'min:1']]);
        $history = $request->route('history');
        $groups = $request->input('year_components', []);
        $years = $request->input('years');
        foreach ($years as $index => &$year) {
            $mode = $year['transcript_mode'] ?? 'detailed';
            $stage = $history?->education_stage ?: CurriculumAreaStage::stageFromLabel($year['stage'] ?? null);
            if ($mode === 'summary') {
                $allowed = HistoryCurriculumCatalog::formations($stage);
                $base = $allowed[0];
                $enabled = $year['enabled_formations'] ?? [];
                $hours = $year['formation_workload_hours'] ?? [];
                if (! is_array($enabled) || ! is_array($hours) || array_diff($enabled, $allowed) || array_diff(array_keys($hours), $allowed)) {
                    throw ValidationException::withMessages(["years.{$index}.formation_workload_hours" => __('Selecione as formações válidas e informe o total de horas de cada uma.')]);
                }
                $totals = [];
                foreach (array_unique([$base, ...$enabled]) as $formation) {
                    $value = $this->number($hours[$formation] ?? null);
                    if (! is_numeric($value) || (float) $value < 0) {
                        throw ValidationException::withMessages(["years.{$index}.formation_workload_hours.{$formation}" => __('Informe o total de horas desta formação.')]);
                    }
                    $totals[$formation] = round((float) $value, 2);
                }
                $year['formation_workload_hours'] = $totals;
                $year['workload_hours'] = round(array_sum($totals), 2);
            } else {
                unset($year['formation_workload_hours']);
            }
            if (in_array($mode, ['summary', 'no_transcription'], true)) {
                // Preserva os lançamentos antigos: o modo global não edita notas individuais.
                $saved = $history instanceof StudentAcademicHistory && ! empty($year['id'])
                    ? $history->years()->where('source', '!=', 'system')->whereKey($year['id'])->with('records.component')->first() : null;
                $groups[$index] = $saved ? $saved->records->map(fn ($record) => [
                    'formation' => $record->component->formation, 'knowledge_area' => $record->component->knowledge_area,
                    'name' => $record->component->name, 'module_label' => $record->component->module_label,
                    'regulatory_reference' => $record->component->regulatory_reference,
                    'record' => $record->only(['score_label', 'score_numeric', 'workload_hours', 'completed_workload_hours', 'frequency_label', 'frequency_percentage', 'absences', 'result']),
                ])->all() : [];
            }
        }
        unset($year);
        $request->merge(['years' => $years, 'year_components' => $groups]);
    }

    private function validatedMatrixData(Request $request, bool $creating = false): array
    {
        $routeHistory = $request->route('history');
        $fixedYearStage = $routeHistory instanceof StudentAcademicHistory && $routeHistory->is_unified
            ? AcademicCourse::STAGE_LABELS[$routeHistory->education_stage] ?? $routeHistory->stage
            : null;

        $request->merge([
            'years' => collect($request->input('years', []))->map(function (array $year) use ($fixedYearStage, $request): array {
                // Série/fase é a identificação apresentada para a coluna e no histórico.
                $year['label'] = $year['grade_phase'] ?? '';
                if (! $request->boolean('formation_totals_present')) {
                    unset($year['formation_workload_hours']);
                }

                if ($fixedYearStage) {
                    $year['stage'] = $fixedYearStage;
                }
                if (! filled($year['grade_number'] ?? null)) {
                    $yearStage = array_search($year['stage'] ?? '', AcademicCourse::STAGE_LABELS, true);
                    $year['grade_number'] = app(StudentAcademicHistoryCompleteness::class)
                        ->gradeNumber((string) ($year['grade_phase'] ?? ''), $yearStage ?: null);
                }

                foreach (['workload_hours', 'minimum_attendance_percentage'] as $field) {
                    if (! isset($year[$field])) {
                        continue;
                    }

                    $year[$field] = $this->number($year[$field]);
                }

                if (isset($year['school_days'])) {
                    $year['school_days'] = preg_replace('/\D/', '', (string) $year['school_days']);
                }

                if (($year['country'] ?? '') === '') {
                    $year['country'] = 'Brasil';
                }

                if (($year['transcript_mode'] ?? '') === '') {
                    $year['transcript_mode'] = 'detailed';
                }

                if (isset($year['workload_hours'])) {
                    $year['workload_hours'] = $this->number($year['workload_hours']);
                }

                return $year;
            })->all(),
            'components' => collect($request->input('components', []))->map(function (array $component): array {
                $component['records'] = collect($component['records'] ?? [])->map(function (array $record): array {
                    foreach (['score_numeric', 'workload_hours', 'completed_workload_hours', 'frequency_percentage'] as $field) {
                        if (isset($record[$field])) {
                            $record[$field] = $this->number($record[$field]);
                        }
                    }

                    if (isset($record['absences'])) {
                        $record['absences'] = preg_replace('/\D/', '', (string) $record['absences']);
                    }

                    return $record;
                })->all();

                return $component;
            })->all(),
        ]);

        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'title' => [$creating ? 'nullable' : 'required', 'string', 'max:255'],
            'stage' => [$creating ? 'nullable' : 'required', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'issued_place' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
            'is_unified' => ['nullable', 'boolean'],
            'years' => ['required', 'array', 'min:1'],
            'years.*.id' => ['nullable', 'integer', 'distinct'],
            'removed_year_ids' => ['nullable', 'array'],
            'removed_year_ids.*' => ['required', 'integer', 'distinct'],
            'years.*.label' => ['required', 'string', 'max:255'],
            'years.*.source' => ['nullable', Rule::in(['manual', 'external', 'system'])],
            'years.*.student_enrollment_id' => ['nullable', 'integer', Rule::exists('student_enrollments', 'id')],
            'years.*.year' => ['required', 'string', 'max:20'],
            'years.*.stage' => ['required', 'string', 'max:255'],
            'years.*.modality' => ['required', 'string', 'max:255'],
            'years.*.grade_phase' => ['required', 'string', 'max:255'],
            'years.*.grade_number' => ['nullable', 'integer', 'between:1,9'],
            'years.*.imported_from_history_year_id' => ['nullable', 'integer'],
            'years.*.school_name' => ['required', 'string', 'max:255'],
            'years.*.school_authorization' => ['nullable', 'string'],
            'years.*.source_document' => ['nullable', 'string', 'max:255'],
            'years.*.city' => ['required', 'string', 'max:255'],
            'years.*.state' => ['required', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'years.*.country' => ['nullable', 'string', 'max:255'],
            'years.*.transcript_mode' => ['required', Rule::in(['detailed', 'summary', 'no_transcription'])],
            'years.*.final_result' => ['required', 'string', 'max:255'],
            'years.*.workload_hours' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'years.*.formation_workload_hours' => ['nullable', 'array'],
            'years.*.formation_workload_hours.*' => ['required', 'numeric', 'min:0'],
            'years.*.school_days' => ['nullable', 'integer', 'min:0', 'max:400'],
            'years.*.attendance_label' => ['nullable', 'string', 'max:255'],
            'years.*.minimum_attendance_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'years.*.notes' => ['nullable', 'string'],
            'components' => ['nullable', 'array'],
            'components.*.formation' => ['nullable', 'string', 'max:255'],
            'components.*.knowledge_area' => ['nullable', 'string', 'max:255'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.module_label' => ['nullable', 'string', 'max:255'],
            'components.*.regulatory_reference' => ['nullable', 'string'],
            'components.*.records' => ['nullable', 'array'],
            'components.*.records.*.registered' => ['nullable', 'boolean'],
            'components.*.records.*.score_label' => ['nullable', 'string', 'max:255'],
            'components.*.records.*.score_numeric' => ['nullable', 'numeric', 'min:0', 'max:10'],
            // A carga horária pertence ao ano/série, não ao lançamento de cada componente.
            // Preserve complementary data transcribed from external documents.
            'components.*.records.*.completed_workload_hours' => ['nullable', 'numeric', 'min:0'],
            'components.*.records.*.workload_hours' => ['nullable', 'numeric', 'min:0'],
            'components.*.records.*.frequency_label' => ['nullable', 'string', 'max:255'],
            'components.*.records.*.frequency_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.records.*.absences' => ['nullable', 'integer', 'min:0', 'max:999'],
            'components.*.records.*.result' => ['nullable', 'string', 'max:255'],
        ]);

        $schoolId = $data['school_id'] ?? null;
        abort_unless($request->user()->canManageSchool($schoolId), 403);
        $studentId = (int) ($request->route('person')?->id ?? 0);
        $existingImportIds = $routeHistory instanceof StudentAcademicHistory
            ? $routeHistory->years()->whereNotNull('imported_from_history_year_id')->pluck('imported_from_history_year_id')->all()
            : [];
        foreach ($data['years'] as $index => $year) {
            abort_unless(empty($year['imported_from_history_year_id'])
                || in_array((int) $year['imported_from_history_year_id'], $existingImportIds, true), 422);
            $yearStage = $routeHistory instanceof StudentAcademicHistory && $routeHistory->is_unified
                ? $routeHistory->education_stage
                : array_search($year['stage'], AcademicCourse::STAGE_LABELS, true);
            if (in_array($yearStage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL], true)
                && (! isset($year['grade_number']) || (int) $year['grade_number'] > ($yearStage === AcademicCourse::STAGE_HIGH_SCHOOL ? 3 : 9))) {
                throw ValidationException::withMessages(["years.{$index}.grade_number" => __('Selecione o ano escolar correspondente à etapa informada.')]);
            }
        }
        $enrollmentIds = collect($data['years'])->pluck('student_enrollment_id')->filter()->map(fn ($id) => (int) $id);
        abort_unless(
            $enrollmentIds->isEmpty() || StudentEnrollment::query()->where('person_id', $studentId)->whereIn('id', $enrollmentIds)->count() === $enrollmentIds->unique()->count(),
            422,
            __('Uma das matrículas informadas não pertence ao estudante deste histórico.'),
        );
        foreach ($data['components'] ?? [] as $index => $component) {
            $componentStages = $routeHistory?->education_stage ? collect([$routeHistory->education_stage])
                : collect($data['years'])->filter(function ($year, $yearIndex) use ($component) {
                    return collect($component['records'][$yearIndex] ?? [])->contains(fn ($value) => filled($value));
                })->pluck('stage')->map(fn ($label) => CurriculumAreaStage::stageFromLabel($label))->filter()->unique();
            if ($componentStages->isEmpty()) {
                $componentStages = collect($data['years'])->pluck('stage')->map(fn ($label) => CurriculumAreaStage::stageFromLabel($label))->filter()->unique();
            }
            foreach ($componentStages as $componentStage) {
                CurriculumAreaStage::validate($component['knowledge_area'] ?? null, $componentStage, "components.{$index}.knowledge_area");
                if ($request->boolean('formation_totals_present') && ! HistoryCurriculumCatalog::contains($componentStage, $component['formation'] ?? null, $component['knowledge_area'] ?? null, $component['name'])) {
                    $existingOnly = $routeHistory instanceof StudentAcademicHistory && ! empty($component['records']);
                    foreach ($component['records'] ?? [] as $yearIndex => $record) {
                        $yearId = $data['years'][$yearIndex]['id'] ?? null;
                        $exists = $existingOnly && $yearId && $routeHistory->components()->where('name', $component['name'])
                            ->where('formation', $component['formation'] ?? null)->where('knowledge_area', $component['knowledge_area'] ?? null)
                            ->whereHas('records', fn ($query) => $query->where('student_academic_history_year_id', $yearId)->whereHas('year', fn ($query) => $query->whereIn('source', ['manual', 'external'])))->exists();
                        if (! $exists) {
                            $existingOnly = false;
                        }
                    }
                    if (! $existingOnly) {
                        throw ValidationException::withMessages(["components.{$index}.name" => __('Selecione um componente ativo do cadastro para esta formação, área e etapa.')]);
                    }
                }

            }
        }

        if ($routeHistory instanceof StudentAcademicHistory && $routeHistory->is_unified) {
            $stage = $routeHistory->education_stage ?: AcademicCourse::STAGE_ELEMENTARY;
            $systemGrades = $routeHistory->years()->where('source', 'system')->get(['grade_number', 'grade_phase'])
                ->map(fn ($year): ?int => $year->grade_number ?: app(StudentAcademicHistoryCompleteness::class)->gradeNumber($year->grade_phase, $stage))
                ->filter()->all();
            foreach ($data['years'] as $index => $year) {
                $grade = $year['grade_number'] ?? app(StudentAcademicHistoryCompleteness::class)->gradeNumber($year['grade_phase'], $stage);
                if ($grade !== null && in_array($grade, $systemGrades, true)) {
                    throw ValidationException::withMessages(["years.{$index}.grade_phase" => __('Esta série já está registrada no sistema e não pode ser lançada manualmente.')]);
                }
            }
            foreach ($data['components'] ?? [] as $index => $component) {
                // Imported transcripts can use historical labels outside the current BNCC catalog.
                $existingImported = $routeHistory->components()
                    ->where('name', $component['name'])
                    ->where('formation', $component['formation'] ?? null)
                    ->where('knowledge_area', $component['knowledge_area'] ?? null)
                    ->whereHas('records.year', fn ($query) => $query->whereIn('source', ['manual', 'external']))
                    ->exists();
                if ($existingImported) {
                    continue;
                }

                $validFormations = HistoryCurriculumCatalog::formations($stage);
                if (! in_array($component['formation'] ?? null, $validFormations, true)) {
                    throw ValidationException::withMessages(["components.{$index}.formation" => __('Selecione uma formação válida para esta etapa.')]);
                }
                if (! HistoryCurriculumCatalog::contains($stage, $component['formation'] ?? null, $component['knowledge_area'] ?? null, $component['name'])) {
                    throw ValidationException::withMessages(["components.{$index}.name" => __('Selecione um componente ativo do cadastro para esta formação, área e etapa.')]);
                }
            }
        }

        return [
            'history' => [
                'school_id' => $schoolId,
                'title' => $data['title'] ?? null,
                'stage' => $data['stage'] ?? null,
                'legal_basis' => $data['legal_basis'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issued_place' => $data['issued_place'] ?? null,
                'issued_date' => $data['issued_date'] ?? null,
                'active' => $request->boolean('active'),
                'is_unified' => $routeHistory instanceof StudentAcademicHistory && $routeHistory->is_unified,
            ],
            'years' => $data['years'],
            'removed_year_ids' => $data['removed_year_ids'] ?? [],
            'components' => $this->mergeEquivalentComponents($data['components'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private function mergeEquivalentComponents(array $components): array
    {
        $merged = [];
        foreach ($components as $index => $component) {
            $key = collect([$component['formation'] ?? '', $component['knowledge_area'] ?? '', $component['name'], $component['module_label'] ?? '', $component['regulatory_reference'] ?? ''])
                ->map(fn ($value) => Str::lower(trim((string) $value)))->join('|');
            if (! isset($merged[$key])) {
                $merged[$key] = $component;

                continue;
            }
            foreach ($component['records'] ?? [] as $yearIndex => $record) {
                foreach ($record as $field => $value) {
                    $previous = $merged[$key]['records'][$yearIndex][$field] ?? null;
                    if ($field !== 'registered' && filled($previous) && filled($value) && (string) $previous !== (string) $value
                        && ! (is_numeric($previous) && is_numeric($value) && (float) $previous === (float) $value)) {
                        throw ValidationException::withMessages(["components.{$index}.records.{$yearIndex}.{$field}" => __('Há valores diferentes para o mesmo componente neste ano. Confira as linhas repetidas.')]);
                    }
                    if (! filled($previous) || $field === 'registered') {
                        $merged[$key]['records'][$yearIndex][$field] = $value;
                    }
                }
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRows(StudentAcademicHistory $history, array $data): void
    {
        foreach ($data['removed_year_ids'] ?? [] as $id) {
            $history->years()->findOrFail($id)->delete();
        }
        $history->components()->delete();

        $yearsByIndex = [];
        foreach (array_values($data['years']) as $index => $yearData) {
            $id = $yearData['id'] ?? null;
            unset($yearData['id']);
            $yearData['position'] = $index + 1;
            if ($id) {
                $year = $history->years()->findOrFail($id);
                $year->update($yearData);
                $yearsByIndex[$index] = $year;
            } else {
                $yearsByIndex[$index] = $history->years()->create($yearData);
            }
        }

        foreach (array_values($data['components']) as $componentIndex => $componentData) {
            $records = $componentData['records'] ?? [];
            unset($componentData['records']);

            $component = $history->components()->create($componentData + [
                'position' => $componentIndex + 1,
            ]);

            foreach ($records as $yearIndex => $recordData) {
                if (! isset($yearsByIndex[(int) $yearIndex])) {
                    continue;
                }

                $registered = (bool) ($recordData['registered'] ?? false);
                unset($recordData['registered']);
                if (! $registered && collect($recordData)->filter(fn ($value) => filled($value))->isEmpty()) {
                    continue;
                }

                $component->records()->create($recordData + [
                    'student_academic_history_year_id' => $yearsByIndex[(int) $yearIndex]->id,
                ]);
            }
        }
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

    private function number(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace(',', '.', (string) $value);
    }
}
