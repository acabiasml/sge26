@extends('layouts.app')

@php
    $roles = \App\Models\PersonSchoolRole::ROLE_LABELS;
    $positions = \App\Models\PersonSchoolRole::POSITION_LABELS;
    $primaryRole = $person->primaryActiveRole();
@endphp

@section('title', 'Pessoa')
@section('page-title', $person->full_name)

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm" href="{{ route('people.edit', $person) }}">Editar pessoa</a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('people.pdf', $person) }}">
        <i class="fas fa-file-pdf fa-sm"></i> Ficha em PDF
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Dados pessoais</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Nome</dt>
                        <dd class="col-sm-7">{{ $person->full_name }}</dd>
                        <dt class="col-sm-5">E-mail institucional</dt>
                        <dd class="col-sm-7">{{ $person->institutional_email }}</dd>
                        <dt class="col-sm-5">CPF</dt>
                        <dd class="col-sm-7">{{ $person->cpf ?: '-' }}</dd>
                        <dt class="col-sm-5">Telefone</dt>
                        <dd class="col-sm-7">{{ $person->phone ?: '-' }}</dd>
                        <dt class="col-sm-5">Endereço</dt>
                        <dd class="col-sm-7">
                            @if ($person->address || $person->city || $person->state)
                                {{ $person->address }}
                                @if ($person->number)
                                    , {{ $person->number }}
                                @endif
                                @if ($person->district)
                                    - {{ $person->district }}
                                @endif
                                <span class="d-block text-muted small">
                                    {{ collect([$person->city, $person->state])->filter()->join(' - ') }}
                                    @if ($person->postal_code)
                                        | CEP {{ $person->postal_code }}
                                    @endif
                                </span>
                                @if ($person->address_complement)
                                    <span class="d-block text-muted small">{{ $person->address_complement }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </dd>
                        <dt class="col-sm-5">Situação</dt>
                        <dd class="col-sm-7">{{ $person->active ? 'Ativa' : 'Inativa' }}</dd>
                        <dt class="col-sm-5">Papel atual</dt>
                        <dd class="col-sm-7">
                            @if ($primaryRole)
                                {{ $primaryRole->label() }}
                                @if ($primaryRole->school)
                                    em {{ $primaryRole->school->name }}
                                @endif
                                <span class="d-block text-muted small">
                                    {{ $primaryRole->started_at?->format('d/m/Y') ?? 'Início indeterminado' }}
                                    até
                                    {{ $primaryRole->ended_at?->format('d/m/Y') ?? 'fim indeterminado' }}
                                </span>
                            @else
                                Sem vínculo ativo
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Novo vínculo</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('people.roles.store', $person) }}">
                        @csrf
                        @include('people.roles._form', ['roleModel' => new \App\Models\PersonSchoolRole(['active' => true]), 'roles' => $roles, 'positions' => $positions, 'schools' => $schools])
                        <button class="btn btn-primary" type="submit">Adicionar vínculo</button>
                    </form>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Vínculos e papéis</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Papel</th>
                                <th>Escola</th>
                                <th>Período</th>
                                <th>Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($person->schoolRoles as $roleModel)
                                @php
                                    $isLastAdministration = $roleModel->isLastActiveAdministrator();
                                @endphp
                                <tr>
                                    <td>{{ $roleModel->label() }}</td>
                                    <td>{{ $roleModel->school?->name ?? 'Global' }}</td>
                                    <td>
                                        {{ $roleModel->started_at?->format('d/m/Y') ?? 'Indeterminado' }}
                                        até
                                        {{ $roleModel->ended_at?->format('d/m/Y') ?? 'indeterminado' }}
                                    </td>
                                    <td>
                                        {{ $roleModel->isActiveForDate() ? 'Ativo' : 'Inativo' }}
                                        @if ($isLastAdministration)
                                            <span class="d-block text-muted small">Última Administração ativa</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="d-inline-flex flex-wrap justify-content-end">
                                            @if ($roleModel->isActiveForDate())
                                                @unless ($isLastAdministration)
                                                    <form method="POST" action="{{ route('people.roles.deactivate', [$person, $roleModel]) }}" class="ml-1 mb-1">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-sm btn-outline-warning" type="submit">Desativar</button>
                                                    </form>
                                                @endunless
                                            @else
                                                <form method="POST" action="{{ route('people.roles.activate', [$person, $roleModel]) }}" class="ml-1 mb-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-success" type="submit">Ativar</button>
                                                </form>
                                            @endif

                                            @unless ($isLastAdministration)
                                                <form method="POST" action="{{ route('people.roles.destroy', [$person, $roleModel]) }}" class="ml-1 mb-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-gray-600">Nenhum vínculo cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Responsáveis e contatos</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('people.relationships.store', $person) }}" class="mb-4">
                        @csrf

                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="related_person_id">Pessoa relacionada</label>
                                <select id="related_person_id" name="related_person_id" class="form-control @error('related_person_id') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach ($relationshipPeople as $relatedPerson)
                                        <option value="{{ $relatedPerson->id }}" @selected(old('related_person_id') == $relatedPerson->id)>
                                            {{ $relatedPerson->full_name }}
                                            @if ($relatedPerson->cpf)
                                                - CPF {{ $relatedPerson->cpf }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('related_person_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-3">
                                <label for="relationship_type">Relação</label>
                                <select id="relationship_type" name="relationship_type" class="form-control @error('relationship_type') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach (\App\Models\PersonRelationship::TYPE_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('relationship_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('relationship_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-4 d-flex align-items-end">
                                <div>
                                    <div class="form-check">
                                        <input id="legal_guardian" name="legal_guardian" value="1" type="checkbox" class="form-check-input" @checked(old('legal_guardian'))>
                                        <label for="legal_guardian" class="form-check-label">Responsável legal</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="emergency_contact" name="emergency_contact" value="1" type="checkbox" class="form-check-input" @checked(old('emergency_contact'))>
                                        <label for="emergency_contact" class="form-check-label">Contato de emergência</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Observações</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button class="btn btn-primary" type="submit">Adicionar relação</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Pessoa</th>
                                <th>Relação</th>
                                <th>Contato</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($person->relationships as $relationship)
                                <tr>
                                    <td>{{ $relationship->relatedPerson?->full_name }}</td>
                                    <td>
                                        {{ $relationship->label() }}
                                        @if ($relationship->legal_guardian)
                                            <span class="d-block text-muted small">Responsável legal</span>
                                        @endif
                                        @if ($relationship->emergency_contact)
                                            <span class="d-block text-muted small">Contato de emergência</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $relationship->relatedPerson?->phone ?: '-' }}
                                        @if ($relationship->notes)
                                            <span class="d-block text-muted small">{{ $relationship->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('people.relationships.destroy', [$person, $relationship]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-gray-600">Nenhuma relação cadastrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
