<div>
    @include('livewire.listings.search')

    <section class="card shadow mb-4" aria-labelledby="justification-filters-title">
        <div class="card-header py-3"><h2 id="justification-filters-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Filtros') }}</h2></div>
        <div class="card-body">
            <form wire:submit="applyFilters" method="GET" action="{{ route('attendance-justifications.index') }}" class="row align-items-end">
                <div class="col-md-3 form-group mb-md-0">
                    <label for="justification_school">{{ __('Escola') }}</label>
                    <select id="justification_school" wire:model.live="filters.school" name="school" class="form-control" >
                        @foreach ($schools as $availableSchool)
                            <option value="{{ $availableSchool->id }}" @selected($school?->id === $availableSchool->id)>{{ $availableSchool->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-md-0">
                    <label for="justification_year">{{ __('Ano letivo') }}</label>
                    <select id="justification_year" wire:model.live="filters.year" name="year" class="form-control" >
                        <option value="">{{ __('Todos os anos') }}</option>
                        @foreach ($academicYears as $availableYear)
                            <option value="{{ $availableYear->id }}" @selected($academicYear?->id === $availableYear->id)>{{ $availableYear->referenceYearsLabel() }} · {{ $availableYear->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-md-0">
                    <label for="justification_class">{{ __('Turma') }}</label>
                    <select id="justification_class" wire:model.live="filters.class" name="class" class="form-control" @disabled(! $academicYear)>
                        <option value="">{{ __('Todas as turmas') }}</option>
                        @foreach ($classes as $availableClass)
                            <option value="{{ $availableClass->id }}" @selected($schoolClass?->id === $availableClass->id)>{{ $availableClass->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-0"><button class="btn btn-outline-primary" type="submit"><i class="fas fa-filter mr-1" aria-hidden="true"></i>{{ __('Filtrar') }}</button></div>
            </form>
        </div>
    </section>

    @if ($school)
        <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas das justificativas') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'nova' : 'registradas' }}">
            <a href="#section-nova" class="sge-section-nav-item" data-academic-tab="nova" role="tab"><i class="fas fa-notes-medical"></i><span>{{ __('Nova justificativa') }}</span></a>
            <a href="#section-registradas" class="sge-section-nav-item" data-academic-tab="registradas" role="tab"><i class="fas fa-list"></i><span>{{ __('Registradas') }}</span><small>{{ $justifications->total() }} {{ __('justificativas') }}</small></a>
        </nav>
        <div class="row">
            <div id="section-nova" class="col-12 mb-4" data-academic-panel="nova" role="tabpanel">
                <section class="card shadow" aria-labelledby="new-justification-title">
                    <div class="card-header py-3"><h2 id="new-justification-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Nova justificativa') }}</h2></div>
                    <div class="card-body" wire:ignore wire:key="new-justification-{{ $school->id }}-{{ $academicYear?->id }}-{{ $schoolClass?->id }}">
                        <p class="small text-muted">{{ __('A justificativa é vinculada à matrícula e vale em todos os componentes curriculares do estudante nessa turma.') }}</p>
                        @if($enrollments->isEmpty())
                            <p class="text-muted mb-0">{{ __('Nenhuma matrícula de ano letivo aberto está disponível para os filtros selecionados.') }}</p>
                        @else
                            @include('attendance-justifications.form')
                        @endif
                    </div>
                </section>
            </div>
            <div id="section-registradas" class="col-12 mb-4" data-academic-panel="registradas" role="tabpanel">
                <section class="card shadow" aria-labelledby="justifications-list-title">
                    <div class="card-header py-3"><h2 id="justifications-list-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Justificativas registradas') }}</h2></div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Estudante') }}</th><th>{{ __('Período') }}</th><th>{{ __('Motivo') }}</th><th>{{ __('Criada por') }}</th><th>{{ __('Última alteração') }}</th><th>{{ __('Alterações') }}</th><th class="text-right">{{ __('Ações') }}</th></tr></thead>
                            <tbody>
                                @forelse ($justifications as $justification)
                                    <tr>
                                        <td>{{ $justification->enrollment?->student?->full_name }}<span class="d-block small text-muted">{{ $justification->enrollment?->schoolClass?->name }} · {{ $justification->enrollment?->schoolClass?->academicYear?->referenceYearsLabel() }}</span></td>
                                        <td>{{ $justification->starts_at?->format('d/m/Y') }} {{ __('a') }} {{ $justification->ends_at?->format('d/m/Y') }}</td>
                                        <td>{{ $justification->reason }}</td>
                                        <td>{{ $justification->grantedBy?->full_name ?? __('Sistema') }}<span class="d-block small text-muted">{{ $justification->created_at?->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</span></td>
                                        <td>{{ $justification->modification_count ? ($justification->updatedBy?->full_name ?? __('Sistema')) : __('Ainda não alterada') }}@if($justification->modification_count)<span class="d-block small text-muted">{{ $justification->updated_at?->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</span>@endif</td>
                                        <td>{{ $justification->modification_count }}</td>
                                        <td class="text-right text-nowrap">
                                            @unless($justification->enrollment?->schoolClass?->academicYear?->isReadOnly())
                                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('attendance-justifications.edit', $justification) }}" aria-label="{{ __('Editar justificativa de') }} {{ $justification->enrollment?->student?->full_name }}" title="{{ __('Editar justificativa') }}"><i class="fas fa-pen" aria-hidden="true"></i></a>
                                                <form class="d-inline" method="POST" action="{{ route('attendance-justifications.destroy', $justification) }}" data-confirm-message="{{ __('Remover esta justificativa?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover justificativa de') }} {{ $justification->enrollment?->student?->full_name }}" title="{{ __('Remover justificativa') }}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7">{{ __('Nenhuma justificativa encontrada para os filtros selecionados.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($justifications->hasPages())
                        <div class="card-footer">{{ $justifications->links('livewire::bootstrap') }}</div>
                    @endif
                </section>
            </div>
        </div>
    @else
        <div class="alert alert-info">{{ __('Nenhuma escola disponível para a sua gestão.') }}</div>
    @endif

</div>
