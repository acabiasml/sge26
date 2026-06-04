<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'person' => auth()->user()->person,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $person = $request->user()->person;

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'social_name' => ['nullable', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:20', Rule::unique('people', 'cpf')->ignore($person)],
            'birth_date' => ['required', 'date'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['active'] = true;
        $validated['institutional_email'] = $person->institutional_email;

        $person->fill($validated);
        $person->profile_completed_at = now();
        $person->save();

        return redirect()->route('dashboard')
            ->with('status', 'Cadastro atualizado com sucesso.');
    }
}
