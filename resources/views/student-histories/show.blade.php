@extends('layouts.app')

@section('title', $history->title)
@section('page-title', $history->title)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action {{ ! $historyCompleteness['complete'] ? 'disabled' : '' }}" @if($historyCompleteness['complete']) href="{{ route('people.histories.pdf', [$person, $history]) }}" target="_blank" @else aria-disabled="true" @endif aria-label="{{ __('Emitir histórico em PDF') }}" title="{{ $historyCompleteness['complete'] ? __('Histórico em PDF') : $historyCompleteness['message'] }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.details.edit', [$person, $history]) }}" aria-label="{{ __('Editar dados gerais do histórico') }}" title="{{ __('Dados gerais') }}">
        <i class="fas fa-file-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.edit', [$person, $history]) }}" aria-label="{{ $history->is_unified ? __('Gerenciar séries externas do histórico') : __('Gerenciar matriz curricular do histórico') }}" title="{{ $history->is_unified ? __('Séries externas') : __('Matriz curricular') }}">
        <i class="fas fa-table" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-histories.student', $person) }}" aria-label="{{ __('Voltar aos históricos') }}" title="{{ __('Voltar aos históricos') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @if(! $historyCompleteness['complete'])
        <div class="alert alert-danger"><strong>{{ __('Emissão bloqueada.') }}</strong> {{ $historyCompleteness['message'] }}</div>
    @endif
    @php($transcriptModeLabels = ['detailed' => __('Detalhada'), 'summary' => __('Global/AP'), 'no_transcription' => __('Sem transcrição')])

    <section class="sge-student-profile mb-4" aria-labelledby="history-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">{{ $history->stage ?: __('Histórico escolar') }}</div>
                <h2 id="history-title">{{ $person->social_name ?: $person->full_name }}</h2>
                <div class="sge-student-meta">
                    @if($person->student_inep)<span><i class="fas fa-id-card" aria-hidden="true"></i>INEP {{ $person->student_inep }}</span>@endif
                    @if($person->nis)<span><i class="fas fa-address-card" aria-hidden="true"></i>NIS {{ $person->nis }}</span>@endif
                    @if($person->cpf)<span><i class="fas fa-fingerprint" aria-hidden="true"></i>CPF {{ $person->cpf }}</span>@endif
                    @if($person->birth_date)<span><i class="fas fa-birthday-cake" aria-hidden="true"></i>{{ $person->birth_date->format('d/m/Y') }}</span>@endif
                    <span><i class="fas fa-school" aria-hidden="true"></i>{{ $history->school?->name ?? __('Escola não vinculada') }}</span>
                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ collect([$history->issued_place, $history->issued_date?->format('d/m/Y')])->filter()->join(', ') ?: __('Sem local/data de emissão') }}</span>
                </div>
            </div>
        </div>
        <div class="sge-student-profile-status">
            <span class="badge badge-{{ $history->active ? 'success' : 'secondary' }}">{{ $history->active ? __('Ativo') : __('Inativo') }}</span>
            <strong>{{ $history->components->count() }}</strong>
            <span>{{ __('componentes') }}</span>
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="{{ __('Resumo do histórico') }}">
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-columns" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Colunas') }}</span>
            <strong>{{ $history->years->count() }}</strong>
            <span class="sge-metric-note">{{ __('anos, séries ou fases') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-book" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Componentes') }}</span>
            <strong>{{ $history->components->count() }}</strong>
            <span class="sge-metric-note">{{ __('linhas curriculares') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-orange">
            <div class="sge-metric-icon"><i class="fas fa-clock" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Carga horária') }}</span>
            <strong>{{ number_format((float) $history->years->sum('workload_hours'), 0, ',', '.') }}</strong>
            <span class="sge-metric-note">{{ __('hora(s) informadas') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-brown">
            <div class="sge-metric-icon"><i class="fas fa-{{ $historyCompleteness['complete'] ? 'check-circle' : 'exclamation-circle' }}" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Emissão') }}</span>
            <strong>{{ $historyCompleteness['complete'] ? __('Liberada') : __('Bloqueada') }}</strong>
            <span class="sge-metric-note">{{ $historyCompleteness['complete'] ? __('histórico completo para PDF') : $historyCompleteness['message'] }}</span>
        </article>
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-sync-alt" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Origem dos dados') }}</span>
            <strong>{{ $history->is_unified ? __('Unificada') : __('Manual') }}</strong>
            <span class="sge-metric-note">{{ $history->is_unified ? __('sistema atual e séries externas') : __('documento recebido') }}</span>
        </article>
    </section>

    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas do histórico escolar') }}" role="tablist" data-section-tabs>
        <a href="#section-componentes" class="sge-section-nav-item" data-academic-tab="componentes" role="tab"><i class="fas fa-book-open"></i><span>{{ __('Componentes') }}</span><small>{{ $history->components->count() }} {{ __('linhas') }}</small></a>
        <a href="#section-informacoes" class="sge-section-nav-item" data-academic-tab="informacoes" role="tab"><i class="fas fa-file-alt"></i><span>{{ __('Informações') }}</span><small>{{ __('dados e estudos realizados') }}</small></a>
    </nav>

    <div class="row">
        <div id="section-componentes" class="col-12" data-academic-panel="componentes" role="tabpanel">
            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>{{ __('Componentes curriculares') }}</h2>
                        <p>{{ __('Resultado preservado conforme o documento recebido da escola de origem.') }}</p>
                    </div>
                </div>
                @if($history->education_stage === \App\Models\AcademicCourse::STAGE_TECHNICAL)
                    @include('student-histories._technical-components-table', ['history' => $history])
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 sge-history-read-table">
                        <thead>
                            <tr>
                                <th>{{ __('Formação') }}</th>
                                <th>{{ __('Período / certificação') }}</th>
                                <th>{{ __('Área') }}</th>
                                <th>{{ __('Componente') }}</th>
                                @foreach($history->years as $year)
                                    <th class="text-center">{{ $year->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history->components->groupBy(fn ($component) => $component->formation ?: '-') as $formation => $formationComponents)
                                @php($formationFirst = true)
                                @foreach($formationComponents->sortBy(fn ($component) => collect([$component->module_label, $component->knowledge_area, $component->position])->filter(fn ($value) => $value !== null)->join('|'))->groupBy(fn ($component) => $component->knowledge_area ?: '-') as $area => $areaComponents)
                                    @php($areaFirst = true)
                                    @foreach($areaComponents as $component)
                                    <tr>
                                        @if($formationFirst)<td rowspan="{{ $formationComponents->count() }}" class="align-middle"><strong>{{ $formation }}</strong></td>@php($formationFirst = false)@endif
                                        <td>{{ $component->module_label ?: '-' }}</td>
                                        @if($areaFirst)<td rowspan="{{ $areaComponents->count() }}" class="align-middle">{{ $area }}</td>@php($areaFirst = false)@endif
                                        <td><strong>{{ $component->name }}</strong></td>
                                        @foreach($history->years as $year)
                                            @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                                            <td class="text-center">@if($record)<strong>{{ $record->score_label ?: '-' }}</strong><span class="d-block small text-muted">{{ __('CH') }} {{ $record->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>@if($record->frequency_label)<span class="d-block small text-muted">{{ __('Freq.') }} {{ $record->frequency_label }}</span>@endif @if($record->absences !== null)<span class="d-block small text-muted">{{ __('Faltas') }} {{ $record->absences }}</span>@endif @else - @endif</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ 4 + $history->years->count() }}" class="text-center text-muted">{{ __('Histórico cadastrado sem transcrição de componentes curriculares.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
                @php($basicFormationComponents = $history->components->where('formation', __('Formação Geral Básica')))
                @php($itineraryComponents = $history->components->where('formation', __('Itinerário Formativo')))
                @php($basicFormationHours = $basicFormationComponents->sum(fn ($component) => (float) $component->records->sum('workload_hours')))
                @php($itineraryHours = $itineraryComponents->sum(fn ($component) => (float) $component->records->sum('workload_hours')))
                @if($basicFormationComponents->isNotEmpty() && $itineraryComponents->isNotEmpty())
                    <div class="row g-3 p-3 border-top">
                        <div class="col-md-6"><strong>{{ __('Formação Geral Básica:') }}</strong> {{ number_format($basicFormationHours, 0, ',', '.') }}h</div>
                        <div class="col-md-6"><strong>{{ __('Itinerário Formativo:') }}</strong> {{ number_format($itineraryHours, 0, ',', '.') }}h</div>
                    </div>
                @endif
            </section>
        </div>

        <div id="section-informacoes" class="col-12" data-academic-panel="informacoes" role="tabpanel">
            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>{{ __('Dados gerais') }}</h2>
                        <p>{{ __('Informações usadas na emissão do documento.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="sge-definition-list mb-0">
                        <dt>{{ __('Fundamento legal') }}</dt>
                        <dd>{{ $history->legal_basis ?: '-' }}</dd>
                        <dt>{{ __('Observações') }}</dt>
                        <dd>{{ $history->notes ?: '-' }}</dd>
                        <dt>{{ __('Emissão') }}</dt>
                        <dd>{{ collect([$history->issued_place, $history->issued_date?->format('d/m/Y')])->filter()->join(', ') ?: '-' }}</dd>
                    </dl>
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>{{ __('Estudos realizados') }}</h2>
                        <p>{{ __('Origem de cada coluna do histórico.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="sge-history-study-list">
                        @if($history->education_stage === \App\Models\AcademicCourse::STAGE_TECHNICAL && ($technicalPeriodDurations ?? collect())->isNotEmpty())
                        @foreach($technicalPeriodDurations as $period)
                            @php($moduleResult = $period->ends_at?->copy()->endOfDay()->isPast() ? __('Concluído') : ($period->starts_at?->isFuture() ? __('A cursar') : __('Cursando')))
                            <article>
                                <strong>{{ $period->name }}</strong>
                                <span>{{ $period->starts_at?->format('d/m/Y') }} {{ __('a') }} {{ $period->ends_at?->format('d/m/Y') }}</span>
                                <span class="badge badge-light mt-1">EPT</span>
                                <small>{{ $history->school?->name ?: __('Escola não informada') }}</small>
                                <small>{{ collect([$history->school?->city, $history->school?->state, 'Brasil'])->filter()->join(' / ') }}</small>
                                <span class="badge badge-light mt-2">{{ $moduleResult }}</span>
                            </article>
                        @endforeach
                        @else
                        @foreach($history->years as $year)
                            <article>
                                <strong>{{ $year->label }}</strong>
                                <span>{{ collect([$year->year, $year->stage, $year->grade_phase])->filter()->join(' · ') ?: __('Sem identificação complementar') }}</span>
                                <span class="badge badge-light mt-1">{{ $transcriptModeLabels[$year->transcript_mode] ?? __('Detalhada') }}</span>
                                <small>{{ $year->school_name ?: __('Escola não informada') }}</small>
                                <small>{{ collect([$year->city, $year->state, $year->country])->filter()->join(' / ') ?: __('Município/UF/país não informado') }}</small>
                                @if($year->school_days || $year->attendance_label || $year->minimum_attendance_percentage)
                                    <small>
                                        {{ collect([
                                            $year->school_days ? $year->school_days.__(' dias letivos') : null,
                                            $year->attendance_label ? __('Freq. ').$year->attendance_label : null,
                                            $year->minimum_attendance_percentage ? __('mín. ').number_format((float) $year->minimum_attendance_percentage, 0, ',', '.').'%' : null,
                                        ])->filter()->join(' · ') }}
                                    </small>
                                @endif
                                @if($year->final_result)
                                    <span class="badge badge-light mt-2">{{ $year->final_result }}</span>
                                @endif
                                @if($year->notes)
                                    <small>{{ $year->notes }}</small>
                                @endif
                            </article>
                        @endforeach
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
