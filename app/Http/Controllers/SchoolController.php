<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageSchools(), 403);

        return view('schools.index', [
            'schools' => School::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManageSchools(), 403);

        return view('schools.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageSchools(), 403);

        $school = School::query()->create($this->validatedData($request));

        return redirect()->route('schools.edit', $school)
            ->with('status', 'Escola cadastrada com sucesso.');
    }

    public function edit(Request $request, School $school): View
    {
        abort_unless($request->user()->canManageSchools(), 403);

        return view('schools.edit', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchools(), 403);

        $school->update($this->validatedData($request, $school));

        return redirect()->route('schools.edit', $school)
            ->with('status', 'Escola atualizada com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?School $school = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:255', Rule::unique('schools', 'cnpj')->ignore($school)],
            'inep' => ['nullable', 'string', 'max:255', Rule::unique('schools', 'inep')->ignore($school)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}
