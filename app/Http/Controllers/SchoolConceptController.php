<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolConcept;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolConceptController extends Controller
{
    public function index(Request $request, School $school): View
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $school->load(['concepts', 'academicCriteria']);

        return view('schools.concepts.index', [
            'school' => $school,
        ]);
    }

    public function updateCriteria(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'dependency_component_limit' => ['nullable', 'integer', 'min:0', 'max:30'],
        ]);

        $school->academicCriteria()->updateOrCreate(
            ['effective_from' => $data['effective_from']],
            ['dependency_component_limit' => $data['dependency_component_limit']]
        );
        $school->update(['dependency_component_limit' => $data['dependency_component_limit']]);

        return redirect()->route('schools.concepts.index', $school)
            ->with('status', 'Critérios acadêmicos atualizados com sucesso.');
    }

    public function storeDefault(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
        ]);

        $school->concepts()
            ->whereDate('effective_from', $data['effective_from'])
            ->delete();

        foreach ($this->defaultConcepts() as $concept) {
            $concept['effective_from'] = $data['effective_from'];
            $school->concepts()->create($concept);
        }

        return redirect()->route('schools.concepts.index', $school)
            ->with('status', 'Tabela padrão de conceitos aplicada. Você ainda pode ajustar os intervalos.');
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $school->concepts()->create($this->validatedConceptData($request));

        return redirect()->route('schools.concepts.index', $school)
            ->with('status', 'Conceito cadastrado com sucesso.');
    }

    public function update(Request $request, School $school, SchoolConcept $concept): RedirectResponse
    {
        abort_unless($concept->school_id === $school->id, 404);
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $concept->update($this->validatedConceptData($request));

        return redirect()->route('schools.concepts.index', $school)
            ->with('status', 'Conceito atualizado com sucesso.');
    }

    public function destroy(Request $request, School $school, SchoolConcept $concept): RedirectResponse
    {
        abort_unless($concept->school_id === $school->id, 404);
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $concept->delete();

        return redirect()->route('schools.concepts.index', $school)
            ->with('status', 'Conceito removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedConceptData(Request $request): array
    {
        foreach (['minimum_score', 'maximum_score'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20'],
            'effective_from' => ['required', 'date'],
            'minimum_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_inclusive' => ['nullable', 'boolean'],
            'maximum_inclusive' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $data['minimum_inclusive'] = $request->boolean('minimum_inclusive');
        $data['maximum_inclusive'] = $request->boolean('maximum_inclusive');

        if ($data['minimum_score'] !== null && $data['maximum_score'] !== null && (float) $data['minimum_score'] >= (float) $data['maximum_score']) {
            throw ValidationException::withMessages([
                'maximum_score' => 'A nota máxima precisa ser maior que a nota mínima.',
            ]);
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultConcepts(): array
    {
        return [
            [
                'name' => 'Ótimo',
                'abbreviation' => 'O',
                'minimum_score' => 9,
                'maximum_score' => null,
                'minimum_inclusive' => false,
                'maximum_inclusive' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Bom',
                'abbreviation' => 'B',
                'minimum_score' => 7.5,
                'maximum_score' => 9,
                'minimum_inclusive' => true,
                'maximum_inclusive' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Suficiente',
                'abbreviation' => 'S',
                'minimum_score' => 6,
                'maximum_score' => 7.5,
                'minimum_inclusive' => true,
                'maximum_inclusive' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Insuficiente',
                'abbreviation' => 'I',
                'minimum_score' => 3,
                'maximum_score' => 6,
                'minimum_inclusive' => true,
                'maximum_inclusive' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Insuficiente Grave',
                'abbreviation' => 'IG',
                'minimum_score' => null,
                'maximum_score' => 3,
                'minimum_inclusive' => false,
                'maximum_inclusive' => false,
                'sort_order' => 5,
            ],
        ];
    }
}
