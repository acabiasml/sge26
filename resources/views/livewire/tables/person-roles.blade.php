@forelse ($person->schoolRoles as $role)
    <span class="badge badge-light">{{ $role->label() }}{{ $role->school ? ' - '.$role->school->name : '' }}</span>
@empty
    <span class="text-gray-600">Sem vínculo</span>
@endforelse
