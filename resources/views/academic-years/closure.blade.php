@extends('layouts.app')

@php($closureErrors = collect($issues)->where('level', 'error'))
@php($warnings = collect($issues)->where('level', 'warning'))

@section('title', 'Conferência de fechamento')
@section('page-title', 'Conferência de fechamento')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.final-results.pdf', $academicYear) }}" aria-label="Emitir resultados finais do ano em PDF" title="Resultados finais do ano em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo" title="Voltar ao ano letivo">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <x-academic-trail :school="$academicYear->school" :academic-year="$academicYear" />

    <section class="card shadow mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <span class="text-uppercase small font-weight-bold text-primary">Fechamento anual</span>
                    <h2 class="h4 mt-1 mb-2">{{ $academicYear->school?->name }}</h2>
                    <p class="mb-0 text-muted">
                        {{ $academicYear->name }} · {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}
                    </p>
                </div>
                <div class="text-md-right mt-3 mt-md-0">
                    @if ($academicYear->isClosed())
                        <span class="badge badge-success p-2">Ano letivo fechado</span>
                    @elseif ($canCloseAcademicYear)
                        <span class="badge badge-success p-2">Pronto para fechar</span>
                    @else
                        <span class="badge badge-warning p-2">Conferência pendente</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card shadow h-100">
                <div class="card-body">
                    <span class="small text-muted">Períodos consolidados</span>
                    <strong class="d-block h3 mb-0">{{ $overview['totals']['consolidated_periods'] }}/{{ $overview['totals']['periods'] }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card shadow h-100">
                <div class="card-body">
                    <span class="small text-muted">Turmas</span>
                    <strong class="d-block h3 mb-0">{{ $overview['totals']['classes'] }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card shadow h-100">
                <div class="card-body">
                    <span class="small text-muted">Matrículas</span>
                    <strong class="d-block h3 mb-0">{{ $overview['totals']['enrollments'] }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card shadow h-100">
                <div class="card-body">
                    <span class="small text-muted">Resultados pendentes</span>
                    <strong class="d-block h3 mb-0">{{ $overview['totals']['pending_results'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    <nav class="sge-section-nav mb-4" aria-label="Seções da conferência de fechamento" data-section-tabs data-default-tab="conferencia">
        <a class="sge-section-nav-item" href="#conferencia" data-academic-tab="conferencia"><i class="fas fa-clipboard-check" aria-hidden="true"></i><span>Conferência</span><small>pendências do fechamento</small></a>
        <a class="sge-section-nav-item" href="#periodos" data-academic-tab="periodos"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>Períodos</span><small>consolidação avaliativa</small></a>
        <a class="sge-section-nav-item" href="#turmas" data-academic-tab="turmas"><i class="fas fa-users" aria-hidden="true"></i><span>Turmas e resultados</span><small>situação final</small></a>
    </nav>

    <div data-academic-panel="conferencia">
    @if ($closureErrors->isNotEmpty() || $warnings->isNotEmpty())
        <section class="card shadow mb-4">
            <div class="card-header py-3">
                <h2 class="h6 m-0 font-weight-bold text-primary">O que precisa ser conferido</h2>
            </div>
            <div class="card-body">
                @foreach ($closureErrors as $issue)
                    <div class="alert alert-warning mb-2">
                        <strong>{{ $issue['message'] }}</strong>
                        @if ($issue['detail'] ?? null)
                            <span class="d-block small">{{ $issue['detail'] }}</span>
                        @endif
                    </div>
                @endforeach
                @foreach ($warnings as $issue)
                    <div class="alert alert-light border mb-2">
                        <strong>{{ $issue['message'] }}</strong>
                        @if ($issue['detail'] ?? null)
                            <span class="d-block small">{{ $issue['detail'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <div class="alert alert-success">
            Nenhuma pendência impeditiva encontrada. O ano letivo pode ser fechado.
        </div>
    @endif
    </div>

    <div data-academic-panel="periodos">
            <section class="card shadow mb-4">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Períodos avaliativos</h2>
                </div>
                <div class="card-body">
                    @forelse ($overview['periods'] as $period)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>{{ $period['name'] }}</strong>
                                    <span class="d-block small text-muted">{{ $period['starts_at'] }} a {{ $period['ends_at'] }}</span>
                                </div>
                                <span class="badge badge-{{ $period['consolidated'] ? 'success' : 'warning' }}">
                                    {{ $period['consolidated'] ? 'Consolidado' : 'Pendente' }}
                                </span>
                            </div>
                            @if ($period['consolidated_at'])
                                <p class="small text-muted mb-0 mt-2">
                                    Consolidado em {{ $period['consolidated_at'] }}
                                    @if ($period['consolidated_by'])
                                        por {{ $period['consolidated_by'] }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="mb-0 text-muted">Nenhum período cadastrado.</p>
                    @endforelse
                </div>
            </section>
    </div>

    <div data-academic-panel="turmas">
            <section class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Turmas e resultados finais</h2>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('academic-years.final-results.pdf', $academicYear) }}">
                        <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i> PDF
                    </a>
                </div>
                <div class="card-body">
                    @forelse ($overview['classes'] as $classSummary)
                        @php($class = $classSummary['class'])
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <strong>{{ $class->name }}</strong>
                                    <span class="d-block small text-muted">{{ $class->courses->pluck('name')->join(' + ') ?: 'Sem matriz' }}</span>
                                </div>
                                <span class="badge badge-{{ $classSummary['pending_results'] === 0 ? 'success' : 'warning' }}">
                                    {{ $classSummary['pending_results'] === 0 ? 'Resultados calculados' : $classSummary['pending_results'].' pendente(s)' }}
                                </span>
                            </div>
                            <div class="small mt-2">
                                <strong>{{ $classSummary['enrollments_count'] }}</strong> matrícula(s)
                                @forelse ($classSummary['results'] as $label => $total)
                                    · {{ $label }}: {{ $total }}
                                @empty
                                    · Nenhum resultado final registrado
                                @endforelse
                            </div>
                            <div class="mt-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('classes.enrollments.index', $class) }}">Ver matrículas</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('classes.final-results.pdf', $class) }}">Ata da turma</a>
                            </div>
                        </div>
                    @empty
                        <p class="mb-0 text-muted">Nenhuma turma cadastrada.</p>
                    @endforelse
                </div>
            </section>
    </div>
@endsection
