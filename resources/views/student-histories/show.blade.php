@extends('layouts.app')

@section('title', $history->title)
@section('page-title', $history->title)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action {{ ! $historyCompleteness['complete'] ? 'disabled' : '' }}" @if($historyCompleteness['complete']) href="{{ route('people.histories.pdf', [$person, $history]) }}" target="_blank" @else aria-disabled="true" @endif aria-label="Emitir histórico em PDF" title="{{ $historyCompleteness['complete'] ? 'Histórico em PDF' : $historyCompleteness['message'] }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.details.edit', [$person, $history]) }}" aria-label="Editar dados gerais do histórico" title="Dados gerais">
        <i class="fas fa-file-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.edit', [$person, $history]) }}" aria-label="{{ $history->is_unified ? 'Gerenciar séries externas do histórico' : 'Gerenciar matriz curricular do histórico' }}" title="{{ $history->is_unified ? 'Séries externas' : 'Matriz curricular' }}">
        <i class="fas fa-table" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-histories.student', $person) }}" aria-label="Voltar aos históricos" title="Voltar aos históricos">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @if(! $historyCompleteness['complete'])
        <div class="alert alert-danger"><strong>Emissão bloqueada.</strong> {{ $historyCompleteness['message'] }}</div>
    @endif
    @php($transcriptModeLabels = ['detailed' => 'Detalhada', 'summary' => 'Global/AP', 'no_transcription' => 'Sem transcrição'])

    <section class="sge-student-profile mb-4" aria-labelledby="history-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">{{ $history->stage ?: 'Histórico escolar' }}</div>
                <h2 id="history-title">{{ $person->social_name ?: $person->full_name }}</h2>
                <div class="sge-student-meta">
                    @if($person->student_inep)<span><i class="fas fa-id-card" aria-hidden="true"></i>INEP {{ $person->student_inep }}</span>@endif
                    <span><i class="fas fa-school" aria-hidden="true"></i>{{ $history->school?->name ?? 'Escola não vinculada' }}</span>
                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ collect([$history->issued_place, $history->issued_date?->format('d/m/Y')])->filter()->join(', ') ?: 'Sem local/data de emissão' }}</span>
                </div>
            </div>
        </div>
        <div class="sge-student-profile-status">
            <span class="badge badge-{{ $history->active ? 'success' : 'secondary' }}">{{ $history->active ? 'Ativo' : 'Inativo' }}</span>
            <strong>{{ $history->components->count() }}</strong>
            <span>componentes</span>
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="Resumo do histórico">
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-columns" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Colunas</span>
            <strong>{{ $history->years->count() }}</strong>
            <span class="sge-metric-note">anos, séries ou fases</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-book" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Componentes</span>
            <strong>{{ $history->components->count() }}</strong>
            <span class="sge-metric-note">linhas curriculares</span>
        </article>
        <article class="sge-metric-card sge-metric-orange">
            <div class="sge-metric-icon"><i class="fas fa-clock" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Carga horária</span>
            <strong>{{ number_format((float) $history->years->sum('workload_hours'), 0, ',', '.') }}</strong>
            <span class="sge-metric-note">hora(s) informadas</span>
        </article>
        <article class="sge-metric-card sge-metric-brown">
            <div class="sge-metric-icon"><i class="fas fa-{{ $historyCompleteness['complete'] ? 'check-circle' : 'exclamation-circle' }}" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Emissão</span>
            <strong>{{ $historyCompleteness['complete'] ? 'Liberada' : 'Bloqueada' }}</strong>
            <span class="sge-metric-note">{{ $historyCompleteness['complete'] ? 'histórico completo para PDF' : $historyCompleteness['message'] }}</span>
        </article>
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-sync-alt" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Origem dos dados</span>
            <strong>{{ $history->is_unified ? 'Unificada' : 'Manual' }}</strong>
            <span class="sge-metric-note">{{ $history->is_unified ? 'sistema atual e séries externas' : 'documento recebido' }}</span>
        </article>
    </section>

    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="Áreas do histórico escolar" role="tablist" data-section-tabs>
        <a href="#section-componentes" class="sge-section-nav-item" data-academic-tab="componentes" role="tab"><i class="fas fa-book-open"></i><span>Componentes</span><small>{{ $history->components->count() }} linhas</small></a>
        <a href="#section-informacoes" class="sge-section-nav-item" data-academic-tab="informacoes" role="tab"><i class="fas fa-file-alt"></i><span>Informações</span><small>dados e estudos realizados</small></a>
    </nav>

    <div class="row">
        <div id="section-componentes" class="col-12" data-academic-panel="componentes" role="tabpanel">
            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>Componentes curriculares</h2>
                        <p>Resultado preservado conforme o documento recebido da escola de origem.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 sge-history-read-table">
                        <thead>
                            <tr>
                                <th>Formação</th>
                                <th>Módulo / certificação</th>
                                <th>Área</th>
                                <th>Componente</th>
                                @foreach($history->years as $year)
                                    <th class="text-center">{{ $year->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history->components->groupBy(fn ($component) => $component->formation ?: '-') as $formation => $formationComponents)
                                @php($formationFirst = true)
                                @foreach($formationComponents->groupBy(fn ($component) => $component->knowledge_area ?: '-') as $area => $areaComponents)
                                    @php($areaFirst = true)
                                    @foreach($areaComponents as $component)
                                    <tr>
                                        @if($formationFirst)<td rowspan="{{ $formationComponents->count() }}" class="align-middle"><strong>{{ $formation }}</strong></td>@php($formationFirst = false)@endif
                                        <td>{{ $component->module_label ?: '-' }}</td>
                                        @if($areaFirst)<td rowspan="{{ $areaComponents->count() }}" class="align-middle">{{ $area }}</td>@php($areaFirst = false)@endif
                                        <td><strong>{{ $component->name }}</strong></td>
                                        @foreach($history->years as $year)
                                            @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                                            <td class="text-center">@if($record)<strong>{{ $record->score_label ?: '-' }}</strong><span class="d-block small text-muted">CH {{ $record->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>@if($record->frequency_label)<span class="d-block small text-muted">Freq. {{ $record->frequency_label }}</span>@endif @if($record->absences !== null)<span class="d-block small text-muted">Faltas {{ $record->absences }}</span>@endif @else - @endif</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ 4 + $history->years->count() }}" class="text-center text-muted">Histórico cadastrado sem transcrição de componentes curriculares.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div id="section-informacoes" class="col-12" data-academic-panel="informacoes" role="tabpanel">
            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>Dados gerais</h2>
                        <p>Informações usadas na emissão do documento.</p>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="sge-definition-list mb-0">
                        <dt>Fundamento legal</dt>
                        <dd>{{ $history->legal_basis ?: '-' }}</dd>
                        <dt>Observações</dt>
                        <dd>{{ $history->notes ?: '-' }}</dd>
                        <dt>Emissão</dt>
                        <dd>{{ collect([$history->issued_place, $history->issued_date?->format('d/m/Y')])->filter()->join(', ') ?: '-' }}</dd>
                    </dl>
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4">
                <div class="sge-panel-header">
                    <div>
                        <h2>Estudos realizados</h2>
                        <p>Origem de cada coluna do histórico.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="sge-history-study-list">
                        @foreach($history->years as $year)
                            <article>
                                <strong>{{ $year->label }}</strong>
                                <span>{{ collect([$year->year, $year->stage, $year->grade_phase])->filter()->join(' · ') ?: 'Sem identificação complementar' }}</span>
                                <span class="badge badge-light mt-1">{{ $transcriptModeLabels[$year->transcript_mode] ?? 'Detalhada' }}</span>
                                <small>{{ $year->school_name ?: 'Escola não informada' }}</small>
                                <small>{{ collect([$year->city, $year->state, $year->country])->filter()->join(' / ') ?: 'Município/UF/país não informado' }}</small>
                                @if($year->school_days || $year->attendance_label || $year->minimum_attendance_percentage)
                                    <small>
                                        {{ collect([
                                            $year->school_days ? $year->school_days.' dias letivos' : null,
                                            $year->attendance_label ? 'Freq. '.$year->attendance_label : null,
                                            $year->minimum_attendance_percentage ? 'mín. '.number_format((float) $year->minimum_attendance_percentage, 0, ',', '.').'%' : null,
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
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
