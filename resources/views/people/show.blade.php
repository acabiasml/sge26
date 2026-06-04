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
        </div>
    </div>
@endsection
