<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Support\BrazilianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->canManagePeople(), 403);

        $query = Person::query()
            ->with('schoolRoles.school')
            ->orderBy('full_name');

        if (! $user->isAdministrator()) {
            $query->whereHas('schoolRoles', function ($roles) use ($user): void {
                $roles->whereIn('school_id', $user->manageableSchoolIds());
            });
        }

        return view('people.index', [
            'people' => $query->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return view('people.create', [
            'schools' => $request->user()->isAdministrator()
                ? School::query()->where('active', true)->orderBy('name')->get()
                : School::query()->whereIn('id', $request->user()->manageableSchoolIds())->orderBy('name')->get(),
            'roles' => $this->availableRoles($request),
            'positions' => PersonSchoolRole::POSITION_LABELS,
            'requiresInitialRole' => ! $request->user()->isAdministrator(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $roleData = $this->validatedInitialRole($request);
        $person = Person::query()->create($this->validatedData($request));

        if ($roleData) {
            $person->schoolRoles()->create($roleData);
        }

        return redirect()->route('people.show', $person)
            ->with('status', 'Pessoa cadastrada com sucesso.');
    }

    public function show(Request $request, Person $person): View
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        return view('people.show', [
            'person' => $person->load(['schoolRoles.school', 'relationships.relatedPerson']),
            'schools' => $request->user()->isAdministrator()
                ? School::query()->where('active', true)->orderBy('name')->get()
                : School::query()->whereIn('id', $request->user()->manageableSchoolIds())->orderBy('name')->get(),
            'relationshipPeople' => $this->relationshipPeople($request, $person),
            'positions' => PersonSchoolRole::POSITION_LABELS,
        ]);
    }

    public function edit(Request $request, Person $person): View
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        return view('people.edit', [
            'person' => $person,
            'lockInstitutionalEmail' => $request->user()->person_id === $person->id,
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        $wasActive = $person->active;
        $data = $this->validatedData($request, $person);

        if ($wasActive && ! $data['active'] && $this->hasOnlyActiveAdministratorRole($person)) {
            throw ValidationException::withMessages([
                'active' => 'Não é possível desativar a única pessoa com Administração ativa no sistema.',
            ]);
        }

        $person->update($data);

        if ($wasActive && ! $person->active) {
            $this->endActiveRoles($person);
        }

        return redirect()->route('people.show', $person)
            ->with('status', 'Pessoa atualizada com sucesso.');
    }

    private function canSeePerson(Request $request, Person $person): bool
    {
        $user = $request->user();

        if ($user->isAdministrator()) {
            return true;
        }

        return $person->schoolRoles()
            ->whereIn('school_id', $user->manageableSchoolIds())
            ->exists();
    }

    private function relationshipPeople(Request $request, Person $person)
    {
        $query = Person::query()
            ->whereKeyNot($person->id)
            ->orderBy('full_name');

        if (! $request->user()->isAdministrator()) {
            $query->whereHas('schoolRoles', function ($roles) use ($request): void {
                $roles->whereIn('school_id', $request->user()->manageableSchoolIds());
            });
        }

        return $query->get(['id', 'full_name', 'cpf', 'phone']);
    }

    /**
     * @return array<string, string>
     */
    private function availableRoles(Request $request): array
    {
        $roles = [
            PersonSchoolRole::ROLE_MANAGER => PersonSchoolRole::ROLE_LABELS[PersonSchoolRole::ROLE_MANAGER],
            PersonSchoolRole::ROLE_TEACHER => PersonSchoolRole::ROLE_LABELS[PersonSchoolRole::ROLE_TEACHER],
            PersonSchoolRole::ROLE_STUDENT => PersonSchoolRole::ROLE_LABELS[PersonSchoolRole::ROLE_STUDENT],
            PersonSchoolRole::ROLE_EMPLOYEE => PersonSchoolRole::ROLE_LABELS[PersonSchoolRole::ROLE_EMPLOYEE],
        ];

        if ($request->user()->isAdministrator()) {
            return [PersonSchoolRole::ROLE_ADMINISTRATOR => PersonSchoolRole::ROLE_LABELS[PersonSchoolRole::ROLE_ADMINISTRATOR]] + $roles;
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatedInitialRole(Request $request): ?array
    {
        $rules = [
            'initial_role' => [$request->user()->isAdministrator() ? 'nullable' : 'required', Rule::in(array_keys($this->availableRoles($request)))],
            'initial_school_id' => [$request->user()->isAdministrator() ? 'nullable' : 'required', 'nullable', 'integer', Rule::exists('schools', 'id')],
            'initial_position' => [
                Rule::requiredIf(fn (): bool => $request->input('initial_role') === PersonSchoolRole::ROLE_MANAGER),
                'nullable',
                Rule::in(array_keys(PersonSchoolRole::POSITION_LABELS)),
            ],
            'initial_started_at' => ['nullable', 'date', Rule::requiredIf(fn (): bool => filled($request->input('initial_role')) && $request->input('initial_role') !== PersonSchoolRole::ROLE_ADMINISTRATOR)],
            'initial_ended_at' => ['nullable', 'date', 'after_or_equal:initial_started_at'],
        ];

        $data = $request->validate($rules);

        if (blank($data['initial_role'] ?? null)) {
            return null;
        }

        $schoolId = $data['initial_school_id'] ?? null;

        abort_if($data['initial_role'] === PersonSchoolRole::ROLE_ADMINISTRATOR && $schoolId !== null, 422);
        abort_unless($request->user()->canAssignRoles($schoolId), 403);

        return [
            'school_id' => $schoolId,
            'role' => $data['initial_role'],
            'position' => $data['initial_role'] === PersonSchoolRole::ROLE_MANAGER
                ? ($data['initial_position'] ?? null)
                : null,
            'active' => true,
            'started_at' => $data['initial_role'] === PersonSchoolRole::ROLE_ADMINISTRATOR
                ? now()->toDateString()
                : ($data['initial_started_at'] ?? null),
            'ended_at' => $data['initial_ended_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Person $person = null): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'social_name' => ['nullable', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:20', Rule::unique('people', 'cpf')->ignore($person)],
            'birth_date' => ['nullable', 'date'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ];

        if (! $person || $request->user()->person_id !== $person->id) {
            $rules['institutional_email'] = ['required', 'email', 'max:255', 'ends_with:@ctjj.org', Rule::unique('people', 'institutional_email')->ignore($person)];
        }

        $data = $request->validate($rules);

        if ($person && $request->user()->person_id === $person->id) {
            $data['institutional_email'] = $person->institutional_email;
        }

        $data['active'] = $request->boolean('active');

        return $data;
    }

    private function endActiveRoles(Person $person): void
    {
        $person->schoolRoles()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>', now()->toDateString());
            })
            ->update([
                'active' => false,
                'ended_at' => now()->toDateString(),
            ]);
    }

    private function hasOnlyActiveAdministratorRole(Person $person): bool
    {
        return $person->schoolRoles()
            ->where('role', PersonSchoolRole::ROLE_ADMINISTRATOR)
            ->get()
            ->contains(fn (PersonSchoolRole $role): bool => $role->isLastActiveAdministrator());
    }
}
