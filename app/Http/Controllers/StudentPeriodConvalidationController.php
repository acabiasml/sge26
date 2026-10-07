<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentPeriodConvalidationController extends Controller
{
    public function sheet(Request $request, StudentEnrollment $enrollment): \Illuminate\View\View
    {
        $this->authorizeEnrollment($request, $enrollment);
        $enrollment->load(['student', 'courses.components.course', 'periodConvalidations']);
        $academicYear = $enrollment->schoolClass->academicYear;
        $periods = $academicYear->periods()->orderBy('starts_at')->get();
        $components = $enrollment->courses->flatMap->components->unique('id')->sortBy('name')->values();
        $records = $enrollment->periodConvalidations;
        $newSheet = $request->boolean('new');
        $sourceSchool = $newSheet ? '' : (string) ($request->query->has('source_school') ? $request->query('source_school') : ($records->sortByDesc('updated_at')->first()?->source_school ?? ''));
        $sourceRecords = $newSheet ? collect() : $records->filter(fn ($record) => (string) $record->source_school === $sourceSchool);

        return view('student-enrollments.origin-sheet', compact('enrollment', 'academicYear', 'periods', 'components', 'records', 'sourceSchool', 'sourceRecords', 'newSheet'));
    }

    public function storeSheet(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);
        $year = $enrollment->schoolClass->academicYear;
        abort_if($year->isReadOnly(), 422, __('Não é possível convalidar lançamentos em ano letivo fechado.'));
        $data = $request->validate([
            'source_school' => ['required', 'string', 'max:255'],
            'previous_source_school' => ['nullable', 'string', 'max:255'],
            'convalidated_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rows' => ['required', 'array', 'max:500'],
            'rows.*' => ['array:score,attendance_lessons,attendance_absences,attendance_justified_absences'],
            'sheet_complete' => ['required', 'in:1'],
        ], ['sheet_complete.required' => __('A ficha não foi recebida por completo. Nenhum dado foi salvo.')]);
        $periodIds = $year->periods()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $componentIds = $enrollment->courses()->with('components')->get()->flatMap->components->pluck('id')->map(fn ($id) => (int) $id)->all();
        $prepared = [];
        foreach ($data['rows'] as $key => $row) {
            if (! preg_match('/^(\d+)_(\d+)$/', (string) $key, $ids)
                || ! in_array((int) $ids[1], $periodIds, true) || ! in_array((int) $ids[2], $componentIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rows' => __('Período ou componente não pertence a esta matrícula.')]);
            }
            if (collect($row)->every(fn ($value) => $value === null || $value === '')) {
                continue;
            }
            $prefix = 'rows.'.$key;
            $row['score'] = is_string($row['score'] ?? null) ? str_replace(',', '.', $row['score']) : ($row['score'] ?? null);
            $validated = \Illuminate\Support\Facades\Validator::make(['rows' => [$key => $row]], [
                $prefix.'.score' => ['required', 'numeric', 'between:0,10'],
                $prefix.'.attendance_lessons' => ['nullable', 'required_with:'.$prefix.'.attendance_absences,'.$prefix.'.attendance_justified_absences', 'integer', 'between:1,999'],
                $prefix.'.attendance_absences' => ['nullable', 'required_with:'.$prefix.'.attendance_justified_absences', 'integer', 'min:0', 'lte:'.$prefix.'.attendance_lessons'],
                $prefix.'.attendance_justified_absences' => ['nullable', 'integer', 'min:0', 'lte:'.$prefix.'.attendance_absences'],
            ], [], [
                $prefix.'.score' => __('Média'), $prefix.'.attendance_lessons' => __('Aulas cursadas na origem'),
                $prefix.'.attendance_absences' => __('Faltas na origem'), $prefix.'.attendance_justified_absences' => __('Faltas justificadas na origem'),
            ])->validate()['rows'][$key];
            $prepared[] = ['academic_period_id' => (int) $ids[1], 'curriculum_component_id' => (int) $ids[2], 'values' => $validated];
        }
        if ($prepared === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['rows' => __('Preencha ao menos um resultado da ficha.')]);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($prepared, $data, $enrollment, $request): void {
            foreach ($prepared as $item) {
                $key = ['student_enrollment_id' => $enrollment->id, 'academic_period_id' => $item['academic_period_id'], 'curriculum_component_id' => $item['curriculum_component_id']];
                $existing = StudentPeriodConvalidation::query()->where($key)->lockForUpdate()->first();
                if ($existing && (string) $existing->source_school !== (string) ($data['previous_source_school'] ?? '')) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['rows' => __('Já existe resultado de outra ficha neste período e componente. Abra a ficha correspondente para editá-lo.')]);
                }
                StudentPeriodConvalidation::query()->updateOrCreate($key, array_merge($item['values'], [
                    'source_school' => $data['source_school'], 'convalidated_at' => $data['convalidated_at'],
                    'notes' => $data['notes'] ?? null, 'convalidated_by_person_id' => $request->user()->person_id,
                ]));
            }
        });

        return redirect()->route('enrollments.origin-sheet', ['enrollment' => $enrollment, 'source_school' => $data['source_school']])
            ->with('status', __('Ficha da escola de origem salva com sucesso.'));
    }

    public function store(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);
        $academicYear = $enrollment->schoolClass->academicYear;
        abort_if($academicYear->isReadOnly(), 422, __('Não é possível convalidar lançamentos em ano letivo fechado.'));

        foreach (['score', 'attendance_lessons', 'attendance_absences', 'attendance_justified_absences'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }

        $data = $request->validate([
            'convalidation_id' => ['nullable', 'integer'],
            'academic_period_id' => ['required', 'integer', Rule::exists('academic_periods', 'id')->where('academic_year_id', $academicYear->id)],
            'curriculum_component_id' => ['required', 'integer'],
            'score' => ['required', 'numeric', 'min:0', 'max:10'],
            'attendance_lessons' => ['nullable', 'required_with:attendance_absences,attendance_justified_absences', 'integer', 'min:1', 'max:999'],
            'attendance_absences' => ['nullable', 'required_with:attendance_justified_absences', 'integer', 'min:0', 'max:999', 'lte:attendance_lessons'],
            'attendance_justified_absences' => ['nullable', 'integer', 'min:0', 'max:999', 'lte:attendance_absences'],
            'source_school' => ['nullable', 'string', 'max:255'],
            'convalidated_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'academic_period_id.required' => __('Selecione o período avaliativo.'),
            'curriculum_component_id.required' => __('Selecione o componente curricular.'),
            'score.required' => __('Informe a média recebida da escola de origem.'),
            'score.numeric' => __('Informe uma média válida.'),
            'score.min' => __('A média não pode ser menor que zero.'),
            'score.max' => __('A média não pode ser maior que dez.'),
            'attendance_lessons.integer' => __('Informe a quantidade de aulas como número inteiro.'),
            'attendance_lessons.min' => __('A quantidade de aulas precisa ser maior que zero.'),
            'attendance_absences.integer' => __('Informe a quantidade de faltas como número inteiro.'),
            'attendance_absences.lte' => __('As faltas não podem ser maiores que a quantidade de aulas.'),
            'attendance_justified_absences.integer' => __('Informe as faltas justificadas como número inteiro.'),
            'attendance_justified_absences.lte' => __('As faltas justificadas não podem ser maiores que o total de faltas.'),
        ]);

        abort_unless(
            $enrollment->courses()
                ->whereHas('components', fn ($query) => $query->where('curriculum_components.id', $data['curriculum_component_id']))
                ->exists(),
            422
        );

        if (! empty($data['convalidation_id'])) {
            $existing = $enrollment->periodConvalidations()->findOrFail($data['convalidation_id']);
            abort_unless((int) $existing->academic_period_id === (int) $data['academic_period_id']
                && (int) $existing->curriculum_component_id === (int) $data['curriculum_component_id'], 422);
        }

        StudentPeriodConvalidation::query()->updateOrCreate(
            [
                'student_enrollment_id' => $enrollment->id,
                'academic_period_id' => $data['academic_period_id'],
                'curriculum_component_id' => $data['curriculum_component_id'],
            ],
            [
                'score' => $data['score'],
                'attendance_lessons' => $data['attendance_lessons'] ?? null,
                'attendance_absences' => $data['attendance_absences'] ?? null,
                'attendance_justified_absences' => $data['attendance_justified_absences'] ?? null,
                'source_school' => $data['source_school'] ?? null,
                'convalidated_at' => $data['convalidated_at'] ?? now('America/Sao_Paulo')->toDateString(),
                'notes' => $data['notes'] ?? null,
                'convalidated_by_person_id' => $request->user()->person_id,
            ]
        );

        return redirect()->route('enrollments.report-card.show', $enrollment)
            ->with('status', __('Resultado parcial convalidado com sucesso.'));
    }

    public function destroy(Request $request, StudentEnrollment $enrollment, StudentPeriodConvalidation $convalidation): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);
        abort_unless($convalidation->student_enrollment_id === $enrollment->id, 404);
        abort_if($enrollment->schoolClass->academicYear->isReadOnly(), 422, __('Não é possível remover convalidação em ano letivo fechado.'));

        $convalidation->delete();

        return redirect()->route('enrollments.report-card.show', $enrollment)
            ->with('status', __('Convalidação removida.'));
    }

    private function authorizeEnrollment(Request $request, StudentEnrollment $enrollment): void
    {
        $enrollment->loadMissing('schoolClass.academicYear');
        abort_unless($enrollment->schoolClass?->academicYear, 404);
        abort_unless($request->user()->canManageSchool($enrollment->schoolClass->academicYear->school_id), 403);
    }
}
