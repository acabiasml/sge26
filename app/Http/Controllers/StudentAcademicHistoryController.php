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
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentAcademicHistoryCompleteness;
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
            'separateHistories' => $person->academicHistories->where('is_unified', false),
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
        $history = $synchronizer->synchronize($person, $schoolId, $request->user()->person_id, $stage);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Histórico unificado atualizado com os dados disponíveis no sistema. Os lançamentos manuais foram preservados.'));
    }

    public function importYear(Request $request, Person $person, string $stage, StudentAcademicHistoryYear $year, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeEnrolledStudent($request, $person);
        abort_unless(in_array($stage, $this->availableHistoryStages($request, $person), true), 404);
        $source = $year->history;
        abort_unless($source && $source->person_id === $person->id && ! $source->is_unified, 404);
        $this->authorizeHistory($request, $person, $source);
        $gradeNumber = $year->grade_number ?: app(StudentAcademicHistoryCompleteness::class)->gradeNumber((string) $year->grade_phase, $stage);
        abort_unless($gradeNumber && $year->stage === (AcademicCourse::STAGE_LABELS[$stage] ?? null), 422,
            __('Indique a etapa e o ano escolar exatos no histórico separado antes de aproveitá-lo.'));

        $schoolId = $person->studentEnrollments()
            ->whereHas('courses', fn ($query) => $query->where('stage', $stage))
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->whereIn('school_id', $request->user()->isAdministrator() ? School::query()->pluck('id') : $request->user()->manageableSchoolIds()))
            ->with('schoolClass.academicYear')->latest('enrolled_at')->first()?->schoolClass?->academicYear?->school_id;
        abort_unless($schoolId, 404);
        $history = $synchronizer->synchronize($person, $schoolId, $request->user()->person_id, $stage);
        if ($history->years->contains(fn ($existing) => ($existing->grade_number ?: app(StudentAcademicHistoryCompleteness::class)->gradeNumber((string) $existing->grade_phase, $stage)) === $gradeNumber
            || $existing->imported_from_history_year_id === $year->id)) {
            return redirect()->route('student-histories.student', $person)
                ->with('status', __('Esta série já consta no histórico unificado. Confira os anos antes de aproveitar novamente.'));
        }

        DB::transaction(function () use ($year, $history, $gradeNumber): void {
            if ($history->years()->where('imported_from_history_year_id', $year->id)->exists()) {
                return;
            }
            $copy = $history->years()->create(collect($year->getAttributes())
                ->except(['id', 'student_academic_history_id', 'created_at', 'updated_at', 'student_enrollment_id'])
                ->merge(['position' => $history->years()->count() + 1, 'source' => 'external', 'grade_number' => $gradeNumber, 'imported_from_history_year_id' => $year->id])
                ->all());
            $year->load('records.component');
            foreach ($year->records as $record) {
                $original = $record->component;
                $component = $history->components()->firstOrCreate([
                    'name' => $original->name,
                    'formation' => $original->formation,
                    'knowledge_area' => $original->knowledge_area,
                    'module_label' => $original->module_label,
                ], [
                    'position' => $history->components()->count() + 1,
                    'regulatory_reference' => $original->regulatory_reference,
                ]);
                $component->records()->create(collect($record->getAttributes())
                    ->except(['id', 'student_academic_history_component_id', 'student_academic_history_year_id', 'created_at', 'updated_at'])
                    ->merge(['student_academic_history_year_id' => $copy->id])->all());
            }
        });

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Ano incorporado ao histórico unificado. Confira a série e a carga horária antes de emitir.'));
    }

    public function editDetails(Request $request, Person $person, StudentAcademicHistory $history): View
    {
        $this->authorizeHistory($request, $person, $history);

        return view('student-histories.edit-details', [
            'person' => $person,
            'history' => $history,
            'schools' => $this->schools($request),
        ]);
    }

    public function updateDetails(Request $request, Person $person, StudentAcademicHistory $history): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);
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

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorizePerson($request, $person);

        $data = $this->validatedData($request, creating: true);
        $school = School::query()->findOrFail($data['history']['school_id']);
        $data['history'] = array_merge($data['history'], [
            'title' => 'Histórico escolar',
            'stage' => Str::limit(collect($data['years'])->pluck('stage')->filter()->unique()->join(', '), 255, ''),
            'legal_basis' => null,
            'notes' => null,
            'issued_place' => collect([$school->city, $school->state])->filter()->join('-') ?: null,
            'issued_date' => null,
            'active' => true,
        ]);
        $history = StudentAcademicHistory::query()->create($data['history'] + [
            'person_id' => $person->id,
            'created_by_person_id' => $request->user()->person_id,
            'updated_by_person_id' => $request->user()->person_id,
        ]);

        $this->syncRows($history, $data);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', __('Histórico escolar cadastrado com sucesso.'));
    }

    public function show(Request $request, Person $person, StudentAcademicHistory $history, StudentAcademicHistoryCompleteness $completeness, UnifiedStudentHistorySynchronizer $synchronizer): View
    {
        $this->authorizeHistory($request, $person, $history);

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

    public function edit(Request $request, Person $person, StudentAcademicHistory $history, UnifiedStudentHistorySynchronizer $synchronizer): View
    {
        $this->authorizeHistory($request, $person, $history);

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
            'schools' => $this->schools($request),
            'states' => $this->states(),
        ]);
    }

    public function update(Request $request, Person $person, StudentAcademicHistory $history, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);

        $data = $this->validatedData($request);
        DB::transaction(function () use ($history, $data, $request, $person, $synchronizer): void {
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
            $year->delete();
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
        abort_unless($person->studentEnrollments()->whereHas('schoolClass.academicYear', fn ($query) => $query->whereIn('school_id', $schoolIds))->exists(), 404);
    }

    /**
     * @return array<int, string>
     */
    private function availableHistoryStages(Request $request, Person $person): array
    {
        $schoolIds = $request->user()->isAdministrator()
            ? School::query()->pluck('id')->all()
            : $request->user()->manageableSchoolIds();

        return $person->studentEnrollments()
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
        $routeHistory = $request->route('history');
        $fixedYearStage = $routeHistory instanceof StudentAcademicHistory && $routeHistory->is_unified
            ? AcademicCourse::STAGE_LABELS[$routeHistory->education_stage] ?? $routeHistory->stage
            : null;

        $request->merge([
            'years' => collect($request->input('years', []))->map(function (array $year) use ($fixedYearStage): array {
                // Série/fase é a identificação apresentada para a coluna e no histórico.
                $year['label'] = $year['grade_phase'] ?? '';

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
                    foreach (['score_numeric', 'workload_hours', 'frequency_percentage'] as $field) {
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
            'years.*.workload_hours' => ['required', 'numeric', 'min:0'],
            'years.*.school_days' => ['nullable', 'integer', 'min:0', 'max:400'],
            'years.*.attendance_label' => ['nullable', 'string', 'max:255'],
            'years.*.minimum_attendance_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'years.*.notes' => ['nullable', 'string'],
            'components' => ['nullable', 'array'],
            'components.*.formation' => ['nullable', 'string', 'max:255'],
            'components.*.knowledge_area' => ['nullable', 'string', 'max:255'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.records' => ['nullable', 'array'],
            'components.*.records.*.score_label' => ['nullable', 'string', 'max:255'],
            'components.*.records.*.score_numeric' => ['nullable', 'numeric', 'min:0', 'max:10'],
            // A carga horária pertence ao ano/série, não ao lançamento de cada componente.
            // Preserve complementary data transcribed from external documents.
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
            $areas = collect(config("curriculum.stages.{$stage}.formations.formacao_geral_basica.areas", []));
            $componentsByArea = $areas->mapWithKeys(fn (array $area): array => [$area['name'] => $area['components'] ?? []]);
            $flexibleFormation = match ($stage) {
                AcademicCourse::STAGE_HIGH_SCHOOL => 'Itinerário Formativo',
                AcademicCourse::STAGE_TECHNICAL => 'Formação Técnica Profissional',
                default => 'Parte Diversificada',
            };

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

                $validFormations = $stage === AcademicCourse::STAGE_TECHNICAL
                    ? [$flexibleFormation]
                    : ['Formação Geral Básica', $flexibleFormation];
                if (! in_array($component['formation'] ?? null, $validFormations, true)) {
                    throw ValidationException::withMessages(["components.{$index}.formation" => __('Selecione uma formação válida para esta etapa.')]);
                }
                if ($stage === AcademicCourse::STAGE_TECHNICAL) {
                    continue;
                }
                if (! $areas->pluck('name')->contains($component['knowledge_area'] ?? null)) {
                    throw ValidationException::withMessages(["components.{$index}.knowledge_area" => __('Selecione uma área de conhecimento da BNCC.')]);
                }
                if (($component['formation'] ?? null) === 'Formação Geral Básica'
                    && ! collect($componentsByArea[$component['knowledge_area']] ?? [])->contains($component['name'])) {
                    throw ValidationException::withMessages(["components.{$index}.name" => __('Selecione um componente da BNCC correspondente à área informada.')]);
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
            'components' => $this->mergeEquivalentComponents($data['components'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private function mergeEquivalentComponents(array $components): array
    {
        return collect($components)
            ->groupBy(fn (array $component): string => Str::lower(trim((string) ($component['knowledge_area'] ?? ''))).'|'.Str::lower(trim($component['name'])))
            ->map(function ($equivalent): array {
                $merged = $equivalent->first();
                $merged['records'] = $equivalent
                    ->pluck('records')
                    ->reduce(function (array $records, array $candidate): array {
                        foreach ($candidate as $yearIndex => $record) {
                            if (! isset($records[$yearIndex]) || collect($records[$yearIndex])->filter(fn ($value) => filled($value))->isEmpty()) {
                                $records[$yearIndex] = $record;
                            }
                        }

                        return $records;
                    }, []);

                return $merged;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRows(StudentAcademicHistory $history, array $data): void
    {
        $history->years()->delete();
        $history->components()->delete();

        $yearsByIndex = [];
        foreach (array_values($data['years']) as $index => $yearData) {
            $yearsByIndex[$index] = $history->years()->create($yearData + [
                'position' => $index + 1,
            ]);
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

                if (collect($recordData)->filter(fn ($value) => filled($value))->isEmpty()) {
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
