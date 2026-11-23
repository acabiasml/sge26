<?php

namespace App\Http\Controllers;

use App\Models\ManagementSubstitution;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Support\CredentialRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementSubstitutionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $schoolIds = $request->user()->isAdministrator() ? null : $request->user()->manageableSchoolIds();
        $schools = School::when($schoolIds !== null, fn ($q) => $q->whereIn('id', $schoolIds))->orderBy('name')->get();
        $staffRoles = [PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_EMPLOYEE];
        $people = Person::whereHas('schoolRoles', fn ($q) => $q->whereIn('role', $staffRoles)->where('active', true)
            ->when($schoolIds !== null, fn ($roles) => $roles->whereIn('school_id', $schoolIds)))->where('active', true)->orderBy('full_name')->get(['id', 'full_name']);
        $items = ManagementSubstitution::with('school', 'person')->when($schoolIds !== null, fn ($q) => $q->whereIn('school_id', $schoolIds))
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))->orderByDesc('starts_on')->paginate(10)->withQueryString();

        return view('credential-registry.substitutions', compact('schools', 'people', 'items'));
    }

    public function store(Request $request, CredentialRegistry $registry)
    {
        $data = $request->validate(['school_id' => ['required', 'integer', 'exists:schools,id'], 'person_id' => ['required', 'integer', 'exists:people,id'],
            'position' => ['required', Rule::in(array_keys(PersonSchoolRole::POSITION_LABELS))], 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'reason' => ['required', 'string', 'max:3000']]);
        $registry->authorize($request->user(), $data['school_id']);
        $person = Person::findOrFail($data['person_id']);
        if (! $person->schoolRoles()->where('school_id', $data['school_id'])->where('active', true)
            ->whereIn('role', [PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_EMPLOYEE])->exists()) {
            throw ValidationException::withMessages(['person_id' => 'Escolha uma pessoa da equipe desta escola.']);
        }
        if (! $person->active || ! $person->hasRequiredIdentityForOfficialUse()) {
            throw ValidationException::withMessages(['person_id' => 'Escolha uma pessoa ativa, com identificação completa no cadastro.']);
        }
        DB::transaction(function () use ($data, $person, $request) {
            School::whereKey($data['school_id'])->lockForUpdate()->firstOrFail();
            $overlap = ManagementSubstitution::where('school_id', $data['school_id'])->where('position', $data['position'])->whereNull('cancelled_at')
                ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'Já existe substituição para esse cargo nesse intervalo.']);
            }
            $role = $person->schoolRoles()->create(['school_id' => $data['school_id'], 'role' => PersonSchoolRole::ROLE_MANAGER, 'position' => $data['position'], 'active' => true,
                'started_at' => $data['starts_on'], 'ended_at' => $data['ends_on']]);
            ManagementSubstitution::create($data + ['role_id' => $role->id, 'created_by_user_id' => $request->user()->id]);
        });

        return back()->with('status', 'Substituição cadastrada. A visão de gestão e as assinaturas respeitam o intervalo informado.');
    }

    public function cancel(Request $request, ManagementSubstitution $substitution, CredentialRegistry $registry)
    {
        $registry->authorize($request->user(), $substitution->school_id);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'min:10', 'max:3000']]);
        DB::transaction(function () use ($substitution, $data) {
            School::whereKey($substitution->school_id)->lockForUpdate()->firstOrFail();
            abort_if($substitution->cancelled_at, 409);
            $substitution->update($data + ['cancelled_at' => now()]);
            $substitution->role->update(['active' => false]);
        });

        return back()->with('status', 'Substituição cancelada. O histórico e os documentos arquivados foram preservados.');
    }
}
