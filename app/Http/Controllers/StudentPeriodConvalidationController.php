<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentPeriodConvalidationController extends Controller
{
    public function store(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);
        $academicYear = $enrollment->schoolClass->academicYear;
        abort_if($academicYear->isClosed(), 422, __('Não é possível convalidar lançamentos em ano letivo fechado.'));

        foreach (['score', 'attendance_lessons', 'attendance_absences', 'attendance_justified_absences'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }

        $data = $request->validate([
            'academic_period_id' => ['required', 'integer', Rule::exists('academic_periods', 'id')->where('academic_year_id', $academicYear->id)],
            'curriculum_component_id' => ['required', 'integer'],
            'score' => ['required', 'numeric', 'min:0', 'max:10'],
            'attendance_lessons' => ['nullable', 'integer', 'min:1', 'max:999'],
            'attendance_absences' => ['nullable', 'integer', 'min:0', 'max:999', 'lte:attendance_lessons'],
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
        abort_if($enrollment->schoolClass->academicYear->isClosed(), 422, __('Não é possível remover convalidação em ano letivo fechado.'));

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
