<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonRoleController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->authorizeRoleChange($request, $data);
        $this->preventIncompleteInactivePersonFromReceivingActiveRole($person, $data['active']);

        $person->schoolRoles()->create($data);

        return redirect()->route('people.show', $person)
            ->with('status', 'Vínculo cadastrado com sucesso.');
    }

    public function update(Request $request, Person $person, PersonSchoolRole $role): RedirectResponse
    {
        abort_unless($role->person_id === $person->id, 404);

        $data = $this->validatedData($request, $role);
        $this->authorizeRoleChange($request, $data);
        $this->preventRemovingLastActiveAdministrator($role, $data);
        $this->preventIncompleteInactivePersonFromReceivingActiveRole($person, $data['active']);

        $role->update($data);

        return redirect()->route('people.show', $person)
            ->with('status', 'Vínculo atualizado com sucesso.');
    }

    public function activate(Request $request, Person $person, PersonSchoolRole $role): RedirectResponse
    {
        abort_unless($role->person_id === $person->id, 404);
        $this->authorizeRoleChange($request, [
            'role' => $role->role,
            'school_id' => $role->school_id,
        ]);
        $this->preventIncompleteInactivePersonFromReceivingActiveRole($person, true);

        $role->update([
            'active' => true,
            'ended_at' => null,
            'started_at' => $role->started_at ?? now()->toDateString(),
        ]);

        return redirect()->route('people.show', $person)
            ->with('status', 'Vínculo ativado com sucesso.');
    }

    public function deactivate(Request $request, Person $person, PersonSchoolRole $role): RedirectResponse
    {
        abort_unless($role->person_id === $person->id, 404);
        $this->authorizeRoleChange($request, [
            'role' => $role->role,
            'school_id' => $role->school_id,
        ]);
        $this->preventRemovingLastActiveAdministrator($role);

        $role->update([
            'active' => false,
            'ended_at' => now()->toDateString(),
        ]);

        return redirect()->route('people.show', $person)
            ->with('status', 'Vínculo desativado com sucesso.');
    }

    public function destroy(Request $request, Person $person, PersonSchoolRole $role): RedirectResponse
    {
        abort_unless($role->person_id === $person->id, 404);
        $this->authorizeRoleChange($request, [
            'role' => $role->role,
            'school_id' => $role->school_id,
        ]);
        $this->preventRemovingLastActiveAdministrator($role);

        $role->delete();

        return redirect()->route('people.show', $person)
            ->with('status', 'Vínculo removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?PersonSchoolRole $roleModel = null): array
    {
        $data = $request->validate([
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'role' => ['required', Rule::in([
                PersonSchoolRole::ROLE_ADMINISTRATOR,
                PersonSchoolRole::ROLE_MANAGER,
                PersonSchoolRole::ROLE_TEACHER,
                PersonSchoolRole::ROLE_STUDENT,
                PersonSchoolRole::ROLE_EMPLOYEE,
            ])],
            'position' => [
                Rule::requiredIf(fn (): bool => $request->input('role') === PersonSchoolRole::ROLE_MANAGER),
                'nullable',
                Rule::in(array_keys(PersonSchoolRole::POSITION_LABELS)),
            ],
            'active' => ['nullable', 'boolean'],
            'started_at' => ['nullable', 'date', Rule::requiredIf(fn (): bool => $request->input('role') !== PersonSchoolRole::ROLE_ADMINISTRATOR)],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['school_id'] = $data['school_id'] ?? null;

        if ($data['role'] === PersonSchoolRole::ROLE_ADMINISTRATOR && ! $roleModel) {
            $data['started_at'] = now()->toDateString();
        }

        if ($data['role'] !== PersonSchoolRole::ROLE_MANAGER) {
            $data['position'] = null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function authorizeRoleChange(Request $request, array $data): void
    {
        $user = $request->user();
        $role = $data['role'];
        $schoolId = $data['school_id'] ?? null;

        abort_if($role === PersonSchoolRole::ROLE_ADMINISTRATOR && ! $user->isAdministrator(), 403);
        abort_if($role === PersonSchoolRole::ROLE_ADMINISTRATOR && $schoolId !== null, 422);
        abort_unless($user->canAssignRoles($schoolId), 403);
    }

    /**
     * @param array<string, mixed>|null $newData
     */
    private function preventRemovingLastActiveAdministrator(PersonSchoolRole $role, ?array $newData = null): void
    {
        if (! $role->isLastActiveAdministrator()) {
            return;
        }

        $keepsAdministratorActive = $newData
            && ($newData['role'] ?? null) === PersonSchoolRole::ROLE_ADMINISTRATOR
            && ($newData['active'] ?? false) === true
            && blank($newData['ended_at'] ?? null);

        if (! $keepsAdministratorActive) {
            throw ValidationException::withMessages([
                'role' => 'Não é possível desativar ou remover o único vínculo ativo de Administração no sistema.',
            ]);
        }
    }

    private function preventIncompleteInactivePersonFromReceivingRole(Person $person): void
    {
        if ($person->hasRequiredIdentityForOfficialUse()) {
            return;
        }

        throw ValidationException::withMessages([
            'person' => 'Informe o CPF antes de atribuir um vínculo ativo a esta pessoa.',
        ]);
    }

    private function preventIncompleteInactivePersonFromReceivingActiveRole(Person $person, bool $active): void
    {
        if (! $active) {
            return;
        }

        $this->preventIncompleteInactivePersonFromReceivingRole($person);
    }
}
