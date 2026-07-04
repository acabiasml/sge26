@extends('layouts.app')

@section('title', $history->title)
@section('page-title', $history->title)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" aria-label="Emitir histórico em PDF" title="Histórico em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.edit', [$person, $history]) }}" aria-label="Editar histórico escolar" title="Editar histórico">
        <i class="fas fa-pen" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.student-map.show', $person) }}" aria-label="Voltar à vida escolar" title="Voltar à vida escolar">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
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
            <div class="sge-metric-icon"><i class="fas fa-file-pdf" aria-hidden="true"></i></div>
            <span class="sge-metric-label">PDF</span>
            <strong>A4</strong>
            <span class="sge-metric-note">com autenticidade</span>
        </article>
    </section>

    <div class="row">
        <div class="col-xl-8">
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
                                <th>Área</th>
                                <th>Componente</th>
                                @foreach($history->years as $year)
                                    <th class="text-center">{{ $year->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history->components as $component)
                                <tr>
                                    <td>{{ $component->formation ?: '-' }}</td>
                                    <td>{{ $component->knowledge_area ?: '-' }}</td>
                                    <td><strong>{{ $component->name }}</strong></td>
                                    @foreach($history->years as $year)
                                        @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                                        <td class="text-center">
                                            @if($record)
                                                <strong>{{ $record->score_label ?: '-' }}</strong>
                                                <span class="d-block small text-muted">CH {{ $record->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>
                                                @if($record->frequency_label)<span class="d-block small text-muted">Freq. {{ $record->frequency_label }}</span>@endif
                                                @if($record->absences !== null)<span class="d-block small text-muted">Faltas {{ $record->absences }}</span>@endif
                                                @if($record->result)<span class="d-block small text-muted">{{ $record->result }}</span>@endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $history->years->count() }}" class="text-center text-muted">Histórico cadastrado sem transcrição de componentes curriculares.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
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
