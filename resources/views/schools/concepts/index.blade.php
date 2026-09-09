@extends('layouts.app')

@section('title', __('Conceitos e critérios'))
@section('page-title', __('Conceitos e critérios'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('schools.edit', $school) }}" aria-label="{{ __('Voltar para a escola') }} {{ $school->name }}" title="{{ __('Voltar') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @php
        $editingConcept = $school->concepts->firstWhere('id', (int) request('edit_concept'));
        $currentConcepts = $school->conceptsForDate(now());
        $currentLimit = $school->dependencyComponentLimitForDate();
        $nextOrder = ($school->concepts->max('sort_order') ?? 0) + 1;
    @endphp

    <section class="sge-concept-hero mb-4" aria-labelledby="concepts-school-title">
        <div class="sge-concept-hero-main">
            <div class="sge-avatar-lg" aria-hidden="true">
                <i class="fas fa-school"></i>
            </div>
            <div>
                <div class="sge-page-kicker">{{ __('Critérios próprios') }}</div>
                <h2 id="concepts-school-title" class="h4 mb-1">{{ $school->name }}</h2>
                <p class="mb-0 text-gray-600">{{ __('Configure conceitos exibidos aos estudantes, regras de dependência e faixas de desempenho da escola.') }}</p>
            </div>
        </div>

        <div class="sge-concept-stat-grid" aria-label="{{ __('Resumo dos critérios') }}">
            <div class="sge-concept-stat">
                <span>{{ $currentConcepts->count() }}</span>
                <small>{{ __('conceitos vigentes') }}</small>
            </div>
            <div class="sge-concept-stat">
                <span>{{ $currentLimit ?? '-' }}</span>
                <small>{{ __('dependência permitida') }}</small>
            </div>
            <div class="sge-concept-stat">
                <span>{{ $school->concepts->count() }}</span>
                <small>{{ __('registros históricos') }}</small>
            </div>
        </div>
    </section>

    <div class="row">
        <div class="col-xl-8">
            @if ($editingConcept)
                <div class="card shadow mb-4 sge-concept-edit-card">
                    <div class="card-header d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <span class="sge-page-kicker">{{ __('Editando') }}</span>
                            <h3 class="h5 mb-1">{{ $editingConcept->name }}</h3>
                            <p class="text-gray-600 mb-0">{{ __('Altere abreviatura, faixa de notas, ordem ou vigência deste conceito.') }}</p>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="{{ __('Cancelar edição de conceito') }}" title="{{ __('Cancelar edição') }}">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        @include('schools.concepts._form', [
                            'action' => route('schools.concepts.update', [$school, $editingConcept]),
                            'method' => 'PUT',
                            'concept' => $editingConcept,
                            'submitLabel' => __('Salvar conceito'),
                        ])
                    </div>
                </div>
            @else
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <span class="font-weight-bold">{{ __('Novo conceito') }}</span>
                    </div>
                    <div class="card-body">
                        @include('schools.concepts._form', [
                            'action' => route('schools.concepts.store', $school),
                            'method' => 'POST',
                            'concept' => new \App\Models\SchoolConcept(['minimum_inclusive' => true, 'maximum_inclusive' => false, 'sort_order' => $nextOrder]),
                            'submitLabel' => __('Adicionar conceito'),
                        ])
                    </div>
                </div>
            @endif

            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">{{ __('Tabela de conceitos') }}</span>
                    <span class="badge badge-light">{{ $school->concepts->count() }} {{ __('conceito(s)') }}</span>
                </div>
                <div class="card-body">
                    <div class="sge-concept-list" role="list">
                        @forelse ($school->concepts as $concept)
                            <article class="sge-concept-item @if($editingConcept?->is($concept)) is-editing @endif" role="listitem">
                                <div class="sge-concept-abbr" aria-hidden="true">{{ $concept->shortLabel() }}</div>
                                <div class="sge-concept-copy">
                                    <div class="sge-concept-title-row">
                                        <h3 class="h6 mb-0">{{ $concept->name }}</h3>
                                        <span class="badge badge-light">{{ __('Ordem') }} {{ $concept->sort_order }}</span>
                                    </div>
                                    <p class="mb-0 text-gray-600">{{ __('Vigente a partir de') }} {{ $concept->effective_from?->format('d/m/Y') ?? __('data não informada') }}</p>
                                </div>
                                <div class="sge-concept-range">
                                    <span>{{ __('Faixa') }}</span>
                                    <strong>{{ $concept->rangeLabel() }}</strong>
                                </div>
                                <div class="sge-concept-actions">
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.concepts.index', ['school' => $school, 'edit_concept' => $concept->id]) }}" aria-label="{{ __('Editar conceito') }} {{ $concept->name }}" title="{{ __('Editar conceito') }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('schools.concepts.destroy', [$school, $concept]) }}" onsubmit="return confirm(@js(__('Remover este conceito?')));">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover conceito') }} {{ $concept->name }}" title="{{ __('Remover conceito') }}">
                                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div class="sge-empty-state">
                                <i class="fas fa-list-check" aria-hidden="true"></i>
                                <p class="mb-0">{{ __('Nenhum conceito cadastrado para esta escola.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">{{ __('Dependência') }}</div>
                <div class="card-body">
                    <p class="text-gray-600">{{ __('Defina quantos componentes curriculares o estudante pode ficar abaixo da pontuação mínima sem retenção automática.') }}</p>
                    <form method="POST" action="{{ route('schools.academic-criteria.update', $school) }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="criteria_effective_from">{{ __('Vigente a partir de') }}</label>
                            <input
                                id="criteria_effective_from"
                                name="effective_from"
                                type="date"
                                class="form-control @error('effective_from') is-invalid @enderror"
                                value="{{ old('effective_from', now()->toDateString()) }}"
                                required
                            >
                            @error('effective_from') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="dependency_component_limit">{{ __('Componentes permitidos em dependência') }}</label>
                            <input
                                id="dependency_component_limit"
                                name="dependency_component_limit"
                                data-mask="digits"
                                data-mask-max="2"
                                inputmode="numeric"
                                autocomplete="off"
                                class="form-control @error('dependency_component_limit') is-invalid @enderror"
                                value="{{ old('dependency_component_limit', $school->dependencyComponentLimitForDate()) }}"
                            >
                            @error('dependency_component_limit') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save" aria-hidden="true"></i> {{ __('Salvar critério') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">{{ __('Padrão sugerido') }}</div>
                <div class="card-body">
                    <p class="text-gray-600">{{ __('Preenche uma vigência com os conceitos do exemplo enviado: Ótimo, Bom, Suficiente, Insuficiente e Insuficiente Grave.') }}</p>
                    <form method="POST" action="{{ route('schools.concepts.default', $school) }}" onsubmit="return confirm(@js(__('Substituir a tabela desta vigência pelos conceitos padrão?')));">
                        @csrf
                        <div class="form-group">
                            <label for="default_effective_from">{{ __('Vigente a partir de') }}</label>
                            <input id="default_effective_from" name="effective_from" type="date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-magic" aria-hidden="true"></i> {{ __('Aplicar padrão') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
