<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonContactController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        abort_unless($this->canManagePerson($request, $person), 403);

        $person->contacts()->create($this->validatedData($request));

        return redirect()->route('people.show', $person)
            ->with('status', 'Contato cadastrado com sucesso.');
    }

    public function update(Request $request, Person $person, PersonContact $contact): RedirectResponse
    {
        abort_unless($contact->person_id === $person->id, 404);
        abort_unless($this->canManagePerson($request, $person), 403);

        $contact->update($this->validatedData($request));

        return redirect()->route('people.show', $person)
            ->with('status', 'Contato atualizado com sucesso.');
    }

    public function destroy(Request $request, Person $person, PersonContact $contact): RedirectResponse
    {
        abort_unless($contact->person_id === $person->id, 404);
        abort_unless($this->canManagePerson($request, $person), 403);

        $contact->delete();

        return redirect()->route('people.show', $person)
            ->with('status', 'Contato removido com sucesso.');
    }

    private function canManagePerson(Request $request, Person $person): bool
    {
        $user = $request->user();

        if ($user->isAdministrator()) {
            return true;
        }

        return $person->schoolRoles()
            ->whereIn('school_id', $user->manageableSchoolIds())
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship_type' => ['required', Rule::in(array_keys(PersonContact::TYPE_LABELS))],
            'cpf' => ['nullable', 'string', 'max:20'],
            'nis' => ['nullable', 'string', 'max:20', 'regex:/^\d{11}$/'],
            'phone' => ['nullable', 'string', 'max:255'],
            'secondary_phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'legal_guardian' => ['nullable', 'boolean'],
            'emergency_contact' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['legal_guardian'] = $request->boolean('legal_guardian');
        $data['emergency_contact'] = $request->boolean('emergency_contact');

        return $data;
    }
}
