<?php

namespace App\Http\Controllers;

use App\Support\BrazilianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        return view('profile.edit', [
            'person' => $user->person,
            'lockInstitutionalEmail' => ! $this->canChangeOwnInstitutionalEmail($user),
            'lockOwnIdentity' => ! $user->isAdministrator(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $person = $request->user()->person;
        $lockOwnIdentity = ! $request->user()->isAdministrator();
        $canChangeInstitutionalEmail = $this->canChangeOwnInstitutionalEmail($request->user());
        $locks = [
            'full_name' => $lockOwnIdentity && filled($person->full_name),
            'cpf' => $lockOwnIdentity && filled($person->cpf),
            'birth_date' => $lockOwnIdentity && filled($person->birth_date),
            'mother_name' => $lockOwnIdentity && filled($person->mother_name),
        ];

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'social_name' => ['nullable', 'string', 'max:255'],
            'cpf' => [
                'required',
                'string',
                'max:20',
                ...($locks['cpf'] ? [] : [Rule::unique('people', 'cpf')->ignore($person)]),
            ],
            'birth_date' => ['required', 'date'],
            'mother_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($canChangeInstitutionalEmail) {
            $validated += $request->validate([
                'institutional_email' => ['required', 'email', 'max:255', 'ends_with:@ctjj.org', Rule::unique('people', 'institutional_email')->ignore($person)],
            ]);
        }

        $validated['active'] = true;

        if (! $canChangeInstitutionalEmail) {
            $validated['institutional_email'] = $person->institutional_email;
        }

        foreach ($locks as $field => $locked) {
            if ($locked) {
                $validated[$field] = $field === 'birth_date'
                    ? $person->birth_date?->toDateString()
                    : $person->{$field};
            }
        }

        $person->fill($validated);
        $person->profile_completed_at = now();
        $person->save();

        return redirect()->route('dashboard')
            ->with('status', 'Cadastro atualizado com sucesso.');
    }

    private function canChangeOwnInstitutionalEmail($user): bool
    {
        return $user->isAdministrator()
            && ! ($user->person?->schoolRoles()
                ->where('role', \App\Models\PersonSchoolRole::ROLE_ADMINISTRATOR)
                ->get()
                ->contains(fn ($role): bool => $role->isLastActiveAdministrator()) ?? false);
    }
}
