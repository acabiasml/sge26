<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Support\BrazilianStates;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentAcademicHistoryCompleteness;
use App\Support\UnifiedStudentHistorySynchronizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            ->paginate(25)
            ->withQueryString();

        return view('student-histories.index', compact('students', 'schools', 'selectedSchoolId', 'isAdministrator'));
    }

    public function student(Request $request, Person $person, StudentAcademicHistoryCompleteness $completeness): View
    {
        $this->authorizeEnrolledStudent($request, $person);

        $person->load(['academicHistories' => fn ($query) => $query->where('is_unified', true)->with('years')->withCount(['years', 'components'])]);

        return view('student-histories.student', [
            'person' => $person,
            'historyCompleteness' => $person->academicHistories->mapWithKeys(fn ($history) => [$history->id => $completeness->evaluate($history)]),
        ]);
    }

    public function unified(Request $request, Person $person, string $stage, UnifiedStudentHistorySynchronizer $synchronizer): RedirectResponse
    {
        $this->authorizeEnrolledStudent($request, $person);
        abort_unless(in_array($stage, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL], true), 404);
        $schoolId = $person->studentEnrollments()
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->whereIn('school_id', $request->user()->isAdministrator() ? School::query()->pluck('id') : $request->user()->manageableSchoolIds()))
            ->with('schoolClass.academicYear')
            ->latest('enrolled_at')
            ->first()
            ?->schoolClass->academicYear->school_id;
        $history = $synchronizer->synchronize($person, $schoolId, $request->user()->person_id, $stage);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', 'Histórico unificado atualizado com os dados disponíveis no sistema. Os lançamentos manuais foram preservados.');
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
            'legal_basis' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'issued_place' => ['required', 'string', 'max:255'],
            'issued_date' => ['required', 'date'],
            'active' => ['nullable', 'boolean'],
        ]);
        abort_unless($request->user()->canManageSchool((int) $data['school_id']), 403);
        $history->update(array_merge($data, [
            'active' => $request->boolean('active'),
            'updated_by_person_id' => $request->user()->person_id,
        ]));

        return redirect()->route('people.histories.show', [$person, $history])->with('status', 'Dados gerais do histórico atualizados.');
    }

    public function create(Request $request, Person $person): View
    {
        $this->authorizePerson($request, $person);

        return view('student-histories.create', [
            'person' => $person,
            'history' => new StudentAcademicHistory([
                'title' => 'Histórico escolar',
                'legal_basis' => 'Lei Federal nº 9.394/1996 (LDB).',
                'issued_place' => 'Jarudore / Poxoréu-MT',
            ]),
            'schools' => $this->schools($request),
            'states' => $this->states(),
        ]);
    }

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorizePerson($request, $person);

        $data = $this->validatedData($request);
        $history = StudentAcademicHistory::query()->create($data['history'] + [
            'person_id' => $person->id,
            'created_by_person_id' => $request->user()->person_id,
            'updated_by_person_id' => $request->user()->person_id,
        ]);

        $this->syncRows($history, $data);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', 'Histórico escolar cadastrado com sucesso.');
    }

    public function show(Request $request, Person $person, StudentAcademicHistory $history, StudentAcademicHistoryCompleteness $completeness): View
    {
        $this->authorizeHistory($request, $person, $history);

        $history->load(['school', 'years.records', 'components.records.year']);

        return view('student-histories.show', [
            'person' => $person,
            'history' => $history,
            'historyCompleteness' => $completeness->evaluate($history),
        ]);
    }

    public function edit(Request $request, Person $person, StudentAcademicHistory $history): View
    {
        $this->authorizeHistory($request, $person, $history);

        return view('student-histories.edit', [
            'person' => $person,
            'history' => $history->load(['school', 'years.records', 'components.records.year']),
            'schools' => $this->schools($request),
            'states' => $this->states(),
        ]);
    }

    public function update(Request $request, Person $person, StudentAcademicHistory $history): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);

        $data = $this->validatedData($request);
        $history->update($data['history'] + [
            'updated_by_person_id' => $request->user()->person_id,
        ]);
        $this->syncRows($history, $data);

        return redirect()->route('people.histories.show', [$person, $history])
            ->with('status', 'Histórico escolar atualizado com sucesso.');
    }

    public function destroy(Request $request, Person $person, StudentAcademicHistory $history): RedirectResponse
    {
        $this->authorizeHistory($request, $person, $history);

        $history->delete();

        return redirect()->route('people.student-map.show', $person)
            ->with('status', 'Histórico escolar removido.');
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
                ->with('status', 'Não é possível emitir histórico escolar sem escola relacionada ao documento.');
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
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('beaba-historico-escolar-'.$person->id.'-'.$history->id.'-'.now()->format('Ymd-His').'.pdf');
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
    private function validatedData(Request $request): array
    {
        $request->merge([
            'years' => collect($request->input('years', []))->map(function (array $year): array {
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
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'string', 'max:255'],
            'legal_basis' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'issued_place' => ['required', 'string', 'max:255'],
            'issued_date' => ['required', 'date'],
            'active' => ['nullable', 'boolean'],
            'is_unified' => ['nullable', 'boolean'],
            'years' => ['required', 'array', 'min:1'],
            'years.*.label' => ['required', 'string', 'max:255'],
            'years.*.source' => ['nullable', Rule::in(['manual', 'system'])],
            'years.*.student_enrollment_id' => ['nullable', 'integer', Rule::exists('student_enrollments', 'id')],
            'years.*.year' => ['required', 'string', 'max:20'],
            'years.*.stage' => ['required', 'string', 'max:255'],
            'years.*.modality' => ['required', 'string', 'max:255'],
            'years.*.grade_phase' => ['required', 'string', 'max:255'],
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
            'components.*.records.*.workload_hours' => ['nullable', 'required_with:components.*.records.*.score_label,components.*.records.*.frequency_label,components.*.records.*.result', 'numeric', 'min:0'],
            'components.*.records.*.frequency_label' => ['nullable', 'string', 'max:255'],
            'components.*.records.*.frequency_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.records.*.absences' => ['nullable', 'integer', 'min:0', 'max:999'],
            'components.*.records.*.result' => ['nullable', 'string', 'max:255'],
        ]);

        $schoolId = $data['school_id'] ?? null;
        abort_unless($request->user()->canManageSchool($schoolId), 403);
        $studentId = (int) ($request->route('person')?->id ?? 0);
        $enrollmentIds = collect($data['years'])->pluck('student_enrollment_id')->filter()->map(fn ($id) => (int) $id);
        abort_unless(
            $enrollmentIds->isEmpty() || StudentEnrollment::query()->where('person_id', $studentId)->whereIn('id', $enrollmentIds)->count() === $enrollmentIds->unique()->count(),
            422,
            'Uma das matrículas informadas não pertence ao estudante deste histórico.',
        );
        $routeHistory = $request->route('history');

        return [
            'history' => [
                'school_id' => $schoolId,
                'title' => $data['title'],
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
