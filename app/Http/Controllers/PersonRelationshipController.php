<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonRelationship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonRelationshipController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        abort_unless($this->canManagePerson($request, $person), 403);

        $data = $request->validate([
            'related_person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id'),
                Rule::notIn([$person->id]),
            ],
            'relationship_type' => ['required', Rule::in(array_keys(PersonRelationship::TYPE_LABELS))],
            'legal_guardian' => ['nullable', 'boolean'],
            'emergency_contact' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $relatedPerson = Person::query()->findOrFail($data['related_person_id']);
        abort_unless($this->canManagePerson($request, $relatedPerson), 403);

        $person->relationships()->updateOrCreate(
            [
                'related_person_id' => $relatedPerson->id,
                'relationship_type' => $data['relationship_type'],
            ],
            [
                'legal_guardian' => $request->boolean('legal_guardian'),
                'emergency_contact' => $request->boolean('emergency_contact'),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()->route('people.show', $person)
            ->with('status', 'Relação cadastrada com sucesso.');
    }

    public function destroy(Request $request, Person $person, PersonRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->person_id === $person->id, 404);
        abort_unless($this->canManagePerson($request, $person), 403);

        $relationship->delete();

        return redirect()->route('people.show', $person)
            ->with('status', 'Relação removida com sucesso.');
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
}
