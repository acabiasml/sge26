@extends('layouts.app')

@php
    $roles = \App\Models\PersonSchoolRole::ROLE_LABELS;
    $positions = \App\Models\PersonSchoolRole::POSITION_LABELS;
    $primaryRole = $person->primaryActiveRole();
    $canDeletePerson = auth()->user()->isAdministrator()
        && $person->schoolRoles->isEmpty()
        && $person->studentEnrollments->isEmpty()
        && $person->academicHistories->isEmpty()
        && $person->issuedDocuments->isEmpty()
        && ! $person->user;
@endphp

@section('title', 'Pessoa')
@section('page-title', $person->full_name)

@section('page-actions')
    @if (auth()->user()->isAdministrator() && config('services.google_workspace.enabled'))
        <form class="d-inline" method="POST" action="{{ route('people.google-workspace.store', $person) }}" onsubmit="return confirm('{{ $person->google_workspace_id ? 'Verificar e atualizar o vínculo desta conta com o Google Workspace?' : 'Criar uma conta real no Google Workspace para este e-mail institucional?' }}');">
            @csrf
            <button class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" type="submit" aria-label="{{ $person->google_workspace_id ? 'Verificar conta no Google Workspace' : 'Criar conta no Google Workspace' }} de {{ $person->full_name }}" title="{{ $person->google_workspace_id ? 'Conta vinculada ao Google Workspace' : 'Criar conta no Google Workspace' }}">
                <i class="fab fa-google" aria-hidden="true"></i>
            </button>
        </form>
    @endif
    @if ($person->studentEnrollments->isNotEmpty() || $person->schoolRoles->contains('role', \App\Models\PersonSchoolRole::ROLE_STUDENT))
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.student-map.show', $person) }}" aria-label="Abrir vida escolar de {{ $person->full_name }}" title="Vida escolar">
            <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
        </a>
    @endif
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('people.edit', $person) }}" aria-label="Editar pessoa {{ $person->full_name }}" title="Editar pessoa">
        <i class="fas fa-pen" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="Emitir ficha em PDF de {{ $person->full_name }}" title="Ficha em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    @if ($canDeletePerson)
        <form class="d-inline" method="POST" action="{{ route('people.destroy', $person) }}" onsubmit="return confirm('Excluir definitivamente este cadastro de pessoa?');">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger shadow-sm sge-icon-action" type="submit" aria-label="Excluir cadastro de {{ $person->full_name }}" title="Excluir cadastro">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="sge-person-summary">
                        <div class="sge-avatar-lg">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
                        <div>
                            <div class="sge-page-kicker">{{ $person->hasActiveRoleForDate() ? 'Com vínculo ativo' : 'Sem vínculo ativo' }}</div>
                            <h2 class="h5 mb-1">{{ $person->social_name ?: $person->full_name }}</h2>
                            <p class="mb-0 text-gray-600">{{ $person->institutional_email ?: 'Sem e-mail institucional' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Dados pessoais</div>
                <div class="card-body">
                    <dl class="sge-definition-list mb-0">
                        <dt>Nome completo</dt>
                        <dd>{{ $person->full_name }}</dd>

                        <dt>CPF</dt>
                        <dd>{{ $person->cpf ?: '-' }}</dd>

                        <dt>INEP do estudante</dt>
                        <dd>{{ $person->student_inep ?: '-' }}</dd>

                        <dt>NIS do estudante</dt>
                        <dd>{{ $person->nis ?: '-' }}</dd>

                        <dt>Auxílio do Governo Federal</dt>
                        <dd>{{ $person->receives_federal_aid ? 'Sim' : 'Não' }}</dd>

                        <dt>Data de nascimento</dt>
                        <dd>{{ $person->birth_date?->format('d/m/Y') ?? '-' }}</dd>

                        <dt>Nome da mãe</dt>
                        <dd>{{ $person->mother_name ?: '-' }}</dd>

                        <dt>Nome do pai</dt>
                        <dd>{{ $person->father_name ?: '-' }}</dd>

                        <dt>Telefone</dt>
                        <dd>{{ $person->phone ?: '-' }}</dd>

                        <dt>Endereço</dt>
                        <dd>
                            @if ($person->address || $person->city || $person->state)
                                {{ $person->address }}
                                @if ($person->number), {{ $person->number }} @endif
                                @if ($person->district) - {{ $person->district }} @endif
                                <span class="d-block text-muted small">
                                    {{ collect([$person->city, $person->state])->filter()->join(' - ') }}
                                    @if ($person->postal_code) | CEP {{ $person->postal_code }} @endif
                                </span>
                                @if ($person->address_complement)
                                    <span class="d-block text-muted small">{{ $person->address_complement }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </dd>

                        <dt>Papel atual</dt>
                        <dd>
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

            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Matrículas acadêmicas</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Ano letivo</th>
                                <th>Turma</th>
                                <th>Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($person->studentEnrollments->sortByDesc(fn ($enrollment) => $enrollment->schoolClass?->academicYear?->reference_year) as $enrollment)
                                <tr>
                                    <td>
                                        {{ $enrollment->schoolClass?->academicYear?->name }}
                                        <span class="d-block text-muted small">
                                            {{ $enrollment->schoolClass?->academicYear?->school?->name }}
                                            · {{ $enrollment->schoolClass?->academicYear?->reference_year }}
                                        </span>
                                        <span class="d-block text-muted small">{{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</span>
                                    </td>
                                    <td>{{ $enrollment->schoolClass?->name }}</td>
                                    <td>
                                        {{ $enrollment->statusLabel() }}
                                        <span class="d-block text-muted small">
                                            {{ $enrollment->enrolled_at?->format('d/m/Y') ?? 'sem data' }}
                                            @if ($enrollment->transferred_at)
                                                até {{ $enrollment->transferred_at->format('d/m/Y') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-outline-success sge-icon-action" href="{{ route('enrollments.report-card.show', $enrollment) }}" aria-label="Abrir boletim de {{ $person->full_name }}" title="Boletim">
                                            <i class="fas fa-chart-line" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}" aria-label="Emitir ficha individual de {{ $person->full_name }}" title="Ficha individual">
                                            <i class="fas fa-file-alt" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-gray-600">Nenhuma matrícula acadêmica cadastrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Históricos escolares</span>
                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.histories.create', $person) }}" aria-label="Cadastrar histórico escolar de {{ $person->full_name }}" title="Novo histórico escolar">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Etapa</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($person->academicHistories as $history)
                                <tr>
                                    <td>
                                        {{ $history->title }}
                                        <span class="d-block text-muted small">{{ $history->school?->name ?? 'Sem escola emissora vinculada' }}</span>
                                    </td>
                                    <td>{{ $history->stage ?: '-' }}</td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="Abrir histórico escolar {{ $history->title }}" title="Abrir histórico">
                                            <i class="fas fa-folder-open" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" aria-label="Emitir histórico escolar em PDF" title="Histórico em PDF">
                                            <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-gray-600">Nenhum histórico escolar cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Novo vínculo</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('people.roles.store', $person) }}">
                        @csrf
                        @include('people.roles._form', [
                            'roleModel' => new \App\Models\PersonSchoolRole(['active' => true]),
                            'roles' => $roles,
                            'positions' => $positions,
                            'schools' => $schools,
                            'fieldPrefix' => 'new_role_',
                        ])
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
                                @php($isLastAdministration = $roleModel->isLastActiveAdministrator())
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
                                        <div class="sge-action-buttons">
                                            @if ($roleModel->isActiveForDate())
                                                @unless ($isLastAdministration)
                                                    <form method="POST" action="{{ route('people.roles.deactivate', [$person, $roleModel]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-sm btn-outline-warning sge-icon-action" type="submit" aria-label="Desativar vínculo {{ $roles[$roleModel->role] ?? $roleModel->role }}" title="Desativar vínculo">
                                                            <i class="fas fa-pause" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                @endunless
                                            @else
                                                <form method="POST" action="{{ route('people.roles.activate', [$person, $roleModel]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-success sge-icon-action" type="submit" aria-label="Ativar vínculo {{ $roles[$roleModel->role] ?? $roleModel->role }}" title="Ativar vínculo">
                                                        <i class="fas fa-play" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            <button class="btn btn-sm btn-outline-primary sge-icon-action" type="button" data-toggle="modal" data-target="#editRoleModal{{ $roleModel->id }}" aria-label="Editar vínculo {{ $roles[$roleModel->role] ?? $roleModel->role }}" title="Editar vínculo">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </button>

                                            @unless ($isLastAdministration)
                                                <form method="POST" action="{{ route('people.roles.destroy', [$person, $roleModel]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover vínculo {{ $roles[$roleModel->role] ?? $roleModel->role }}" title="Remover vínculo">
                                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                    </button>
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

            @foreach ($person->schoolRoles as $roleModel)
                <div class="modal fade" id="editRoleModal{{ $roleModel->id }}" tabindex="-1" role="dialog" aria-labelledby="editRoleModal{{ $roleModel->id }}Label" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('people.roles.update', [$person, $roleModel]) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="editRoleModal{{ $roleModel->id }}Label">Editar vínculo</h2>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    @include('people.roles._form', [
                                        'roleModel' => $roleModel,
                                        'roles' => $roles,
                                        'positions' => $positions,
                                        'schools' => $schools,
                                        'fieldPrefix' => 'edit_role_' . $roleModel->id . '_',
                                    ])
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-save" aria-hidden="true"></i> Salvar vínculo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Responsáveis e contatos</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('people.contacts.store', $person) }}" class="mb-4">
                        @csrf

                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="contact_name">Nome</label>
                                <input id="contact_name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-3">
                                <label for="contact_relationship_type">Relação</label>
                                <select id="contact_relationship_type" name="relationship_type" class="form-control @error('relationship_type') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach (\App\Models\PersonContact::TYPE_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('relationship_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('relationship_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-2">
                                <label for="contact_cpf">CPF</label>
                                <input id="contact_cpf" name="cpf" data-mask="cpf" inputmode="numeric" autocomplete="off" class="form-control @error('cpf') is-invalid @enderror" value="{{ old('cpf') }}">
                                @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label for="contact_nis">NIS</label>
                                <input id="contact_nis" name="nis" data-mask="digits" data-mask-max="11" inputmode="numeric" autocomplete="off" class="form-control @error('nis') is-invalid @enderror" value="{{ old('nis') }}">
                                @error('nis') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="contact_phone">Telefone</label>
                                <input id="contact_phone" name="phone" data-mask="phone" inputmode="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-4">
                                <label for="contact_secondary_phone">Telefone alternativo</label>
                                <input id="contact_secondary_phone" name="secondary_phone" data-mask="phone" inputmode="tel" class="form-control @error('secondary_phone') is-invalid @enderror" value="{{ old('secondary_phone') }}">
                                @error('secondary_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group col-md-4">
                                <label for="contact_email">E-mail pessoal</label>
                                <input id="contact_email" name="email" type="email" inputmode="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <div class="form-check">
                                    <input id="contact_legal_guardian" name="legal_guardian" value="1" type="checkbox" class="form-check-input" @checked(old('legal_guardian'))>
                                    <label for="contact_legal_guardian" class="form-check-label">Responsável legal</label>
                                </div>
                                <div class="form-check">
                                    <input id="contact_emergency_contact" name="emergency_contact" value="1" type="checkbox" class="form-check-input" @checked(old('emergency_contact'))>
                                    <label for="contact_emergency_contact" class="form-check-label">Contato de emergência</label>
                                </div>
                            </div>

                            <div class="form-group col-md-8">
                                <label for="contact_notes">Observações</label>
                                <textarea id="contact_notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit">Adicionar contato</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table id="contacts-table" class="table mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Relação</th>
                                <th>Contato</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($editingContactId = (int) old('_editing_contact_id', request('edit_contact')))
                            @forelse ($person->contacts as $contact)
                                <tr>
                                    <td>{{ $contact->name }}</td>
                                    <td>
                                        {{ $contact->label() }}
                                        @if ($contact->legal_guardian)
                                            <span class="d-block text-muted small">Responsável legal</span>
                                        @endif
                                        @if ($contact->emergency_contact)
                                            <span class="d-block text-muted small">Contato de emergência</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $contact->phone ?: '-' }}
                                        @if ($contact->nis)
                                            <span class="d-block text-muted small">NIS {{ $contact->nis }}</span>
                                        @endif
                                        @if ($contact->secondary_phone)
                                            <span class="d-block text-muted small">{{ $contact->secondary_phone }}</span>
                                        @endif
                                        @if ($contact->email)
                                            <span class="d-block text-muted small">{{ $contact->email }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right sge-actions-cell">
                                        <div class="sge-row-actions" role="group" aria-label="Ações para contato {{ $contact->name }}">
                                            <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.show', ['person' => $person, 'edit_contact' => $contact->id]) }}#contact-editor-{{ $contact->id }}" aria-label="Editar contato {{ $contact->name }}" title="Editar contato">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </a>
                                            <form method="POST" action="{{ route('people.contacts.destroy', [$person, $contact]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover contato {{ $contact->name }}" title="Remover contato">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @if ($editingContactId === $contact->id)
                                    <tr class="sge-contact-editor-row" id="contact-editor-{{ $contact->id }}">
                                        <td colspan="4">
                                            <div class="sge-contact-editor-panel">
                                                <div class="sge-contact-editor-heading">
                                                    <div>
                                                        <span>Editando contato</span>
                                                        <strong>{{ $contact->name }}</strong>
                                                    </div>
                                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('people.show', $person) }}#contacts-table">Cancelar</a>
                                                </div>

                                                <form method="POST" action="{{ route('people.contacts.update', [$person, $contact]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="_editing_contact_id" value="{{ $contact->id }}">
                                                    @php($useOldContact = $editingContactId === $contact->id)

                                                    <div class="form-row">
                                                        <div class="form-group col-lg-5 col-md-6">
                                                            <label for="contact_name_{{ $contact->id }}">Nome</label>
                                                            <input id="contact_name_{{ $contact->id }}" name="name" class="form-control" value="{{ $useOldContact ? old('name', $contact->name) : $contact->name }}" required>
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6">
                                                            <label for="contact_relationship_type_{{ $contact->id }}">Relação</label>
                                                            <select id="contact_relationship_type_{{ $contact->id }}" name="relationship_type" class="form-control" required>
                                                                @foreach (\App\Models\PersonContact::TYPE_LABELS as $value => $label)
                                                                    <option value="{{ $value }}" @selected(($useOldContact ? old('relationship_type', $contact->relationship_type) : $contact->relationship_type) === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div class="form-group col-lg-2 col-md-6">
                                                            <label for="contact_cpf_{{ $contact->id }}">CPF</label>
                                                            <input id="contact_cpf_{{ $contact->id }}" name="cpf" data-mask="cpf" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $useOldContact ? old('cpf', $contact->cpf) : $contact->cpf }}">
                                                        </div>
                                                        <div class="form-group col-lg-2 col-md-6">
                                                            <label for="contact_nis_{{ $contact->id }}">NIS</label>
                                                            <input id="contact_nis_{{ $contact->id }}" name="nis" data-mask="digits" data-mask-max="11" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $useOldContact ? old('nis', $contact->nis) : $contact->nis }}">
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-lg-4 col-md-6">
                                                            <label for="contact_phone_{{ $contact->id }}">Telefone</label>
                                                            <input id="contact_phone_{{ $contact->id }}" name="phone" data-mask="phone" inputmode="tel" class="form-control" value="{{ $useOldContact ? old('phone', $contact->phone) : $contact->phone }}">
                                                        </div>

                                                        <div class="form-group col-lg-4 col-md-6">
                                                            <label for="contact_secondary_phone_{{ $contact->id }}">Telefone alternativo</label>
                                                            <input id="contact_secondary_phone_{{ $contact->id }}" name="secondary_phone" data-mask="phone" inputmode="tel" class="form-control" value="{{ $useOldContact ? old('secondary_phone', $contact->secondary_phone) : $contact->secondary_phone }}">
                                                        </div>

                                                        <div class="form-group col-lg-4 col-md-12">
                                                            <label for="contact_email_{{ $contact->id }}">E-mail pessoal</label>
                                                            <input id="contact_email_{{ $contact->id }}" name="email" type="email" inputmode="email" class="form-control" value="{{ $useOldContact ? old('email', $contact->email) : $contact->email }}">
                                                        </div>
                                                    </div>

                                                    <div class="form-row align-items-end">
                                                        <div class="form-group col-lg-4">
                                                            <div class="sge-contact-checks">
                                                                <div class="form-check">
                                                                    <input name="legal_guardian" value="0" type="hidden">
                                                                    <input id="contact_legal_guardian_{{ $contact->id }}" name="legal_guardian" value="1" type="checkbox" class="form-check-input" @checked($useOldContact ? old('legal_guardian', $contact->legal_guardian) : $contact->legal_guardian)>
                                                                    <label for="contact_legal_guardian_{{ $contact->id }}" class="form-check-label">Responsável legal</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input name="emergency_contact" value="0" type="hidden">
                                                                    <input id="contact_emergency_contact_{{ $contact->id }}" name="emergency_contact" value="1" type="checkbox" class="form-check-input" @checked($useOldContact ? old('emergency_contact', $contact->emergency_contact) : $contact->emergency_contact)>
                                                                    <label for="contact_emergency_contact_{{ $contact->id }}" class="form-check-label">Contato de emergência</label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="form-group col-lg-8">
                                                            <label for="contact_notes_{{ $contact->id }}">Observações</label>
                                                            <textarea id="contact_notes_{{ $contact->id }}" name="notes" rows="2" class="form-control">{{ $useOldContact ? old('notes', $contact->notes) : $contact->notes }}</textarea>
                                                        </div>
                                                    </div>

                                                    <div class="sge-contact-editor-actions">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fas fa-save" aria-hidden="true"></i> Salvar contato
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-gray-600">Nenhum contato cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
