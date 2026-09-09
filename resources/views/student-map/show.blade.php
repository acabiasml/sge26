@extends('layouts.app')

@php
    $activeEnrollments = $enrollments->filter->isActive();
    $absenceCount = $attendanceSummary->sum('absent');
    $totalLessons = $attendanceSummary->sum('total');
    $presentLessons = $attendanceSummary->sum('present') + $attendanceSummary->sum('excused');
    $attendanceRate = $totalLessons > 0 ? round(($presentLessons / $totalLessons) * 100, 1) : null;
    $resultsByEnrollment = $assessmentResults->groupBy(fn ($result) => $result->enrollment?->id ?? 'sem-matricula');
    $behaviorByEnrollment = $behaviorGrades->groupBy(fn ($grade) => $grade->enrollment?->id ?? 'sem-matricula');
    $latestResults = $assessmentResults->take(5);
    $histories = $person->academicHistories->sortByDesc('created_at')->values();
    $convalidations = $enrollments
        ->flatMap(fn ($enrollment) => $enrollment->periodConvalidations->map(fn ($item) => ['enrollment' => $enrollment, 'convalidation' => $item]))
        ->sortByDesc(fn ($item) => $item['convalidation']->convalidated_at?->toDateString())
        ->values();

    $scoreLabel = function ($result) use ($scoreView): string {
        if ($scoreView === 'conceitos') {
            $school = $result->enrollment?->schoolClass?->academicYear?->school;
            $referenceDate = $result->assessment?->period?->ends_at ?? $result->assessment?->period?->starts_at;
            $concept = $school?->conceptForScore((float) $result->score, $referenceDate);

            if ($concept) {
                return $concept->shortLabel();
            }
        }

        return number_format((float) $result->score, 1, ',', '.');
    };

    $behaviorLabel = function ($grade) use ($scoreView): string {
        if (! $grade) {
            return __('Pendente');
        }

        if ($scoreView === 'conceitos') {
            $school = $grade->enrollment?->schoolClass?->academicYear?->school;
            $referenceDate = $grade->academicPeriod?->ends_at ?? $grade->academicPeriod?->starts_at;
            $concept = $school?->conceptForScore((float) $grade->score, $referenceDate);

            if ($concept) {
                return $concept->shortLabel();
            }
        }

        return number_format((float) $grade->score, 1, ',', '.');
    };
@endphp

@section('title', __('Vida escolar'))
@section('page-title', __('Vida escolar'))

@section('page-actions')
    @if ($canManagePerson)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.create', $person) }}" aria-label="{{ __('Cadastrar histórico escolar de') }} {{ $person->full_name }}" title="{{ __('Novo histórico escolar') }}">
            <i class="fas fa-folder-plus" aria-hidden="true"></i>
        </a>
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="{{ __('Voltar ao cadastro de') }} {{ $person->full_name }}" title="{{ __('Voltar ao cadastro') }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @endif
    @if ($canManagePerson)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="{{ __('Emitir ficha cadastral em PDF de') }} {{ $person->full_name }}" title="{{ __('Ficha cadastral em PDF') }}">
            <i class="fas fa-file-pdf" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <section class="sge-student-profile sge-life-hero mb-4" aria-labelledby="student-life-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">{{ __('Vida escolar') }}</div>
                <h2 id="student-life-title">{{ $person->social_name ?: $person->full_name }}</h2>
                <div class="sge-student-meta">
                    <span><i class="fas fa-envelope" aria-hidden="true"></i>{{ $person->institutional_email ?: __('E-mail institucional não informado') }}</span>
                    @if ($person->student_inep)
                        <span><i class="fas fa-id-card" aria-hidden="true"></i>INEP {{ $person->student_inep }}</span>
                    @endif
                    <span><i class="fas fa-user-check" aria-hidden="true"></i>{{ $person->hasActiveRoleForDate() ? __('Com vínculo ativo') : __('Sem vínculo ativo') }}</span>
                </div>
            </div>
        </div>
        <div class="sge-life-actions">
            @if ($activeEnrollments->first())
                <a class="btn btn-sm btn-primary" href="{{ route('enrollments.report-card.show', $activeEnrollments->first()) }}">
                    <i class="fas fa-chart-line mr-1" aria-hidden="true"></i>{{ __('Boletim atual') }}
                </a>
                @if ($canManagePerson)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.individual-record.pdf', $activeEnrollments->first()) }}">
                        <i class="fas fa-file-alt mr-1" aria-hidden="true"></i>{{ __('Ficha individual') }}
                    </a>
                @endif
            @endif
            @if ($canManagePerson)
                <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.create', $person) }}">
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>{{ __('Histórico recebido') }}
                </a>
            @endif
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="{{ __('Resumo da vida escolar') }}">
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Matrículas ativas') }}</span>
            <strong>{{ $activeEnrollments->count() }}</strong>
            <span class="sge-metric-note">{{ $enrollments->count() }} {{ __('matrícula(s) no percurso') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Resultados') }}</span>
            <strong>{{ $assessmentResults->count() }}</strong>
            <span class="sge-metric-note">{{ $convalidations->count() }} {{ __('convalidação(ões)') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-orange">
            <div class="sge-metric-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Frequência') }}</span>
            <strong>{{ $attendanceRate !== null ? number_format($attendanceRate, 1, ',', '.') . '%' : '-' }}</strong>
            <span class="sge-metric-note">{{ $absenceCount }} {{ __('falta(s) registrada(s)') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-brown">
            <div class="sge-metric-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Históricos') }}</span>
            <strong>{{ $histories->count() }}</strong>
            <span class="sge-metric-note">{{ __('documento(s) recebido(s)') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Documentos emitidos') }}</span>
            <strong>{{ $documents->count() }}</strong>
            <span class="sge-metric-note">{{ __('até os 12 mais recentes') }}</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
            <span class="sge-metric-label">{{ __('Contatos') }}</span>
            <strong>{{ $person->contacts->count() }}</strong>
            <span class="sge-metric-note">{{ __('responsáveis e contatos cadastrados') }}</span>
        </article>
    </section>

    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas da vida escolar') }}" role="tablist" data-section-tabs>
        <a href="#section-percurso" class="sge-section-nav-item" data-academic-tab="percurso" role="tab"><i class="fas fa-route"></i><span>{{ __('Percurso') }}</span><small>{{ $enrollments->count() }} {{ __('matrículas') }}</small></a>
        <a href="#section-historicos" class="sge-section-nav-item" data-academic-tab="historicos" role="tab"><i class="fas fa-folder-open"></i><span>{{ __('Históricos') }}</span><small>{{ $histories->count() }} {{ __('recebidos') }}</small></a>
        <a href="#section-desempenho" class="sge-section-nav-item" data-academic-tab="desempenho" role="tab"><i class="fas fa-chart-line"></i><span>{{ __('Desempenho') }}</span><small>{{ $assessmentResults->count() }} {{ __('resultados') }}</small></a>
        <a href="#section-frequencia" class="sge-section-nav-item" data-academic-tab="frequencia" role="tab"><i class="fas fa-calendar-check"></i><span>{{ __('Frequência') }}</span><small>{{ $absenceCount }} {{ __('faltas') }}</small></a>
        <a href="#section-contatos" class="sge-section-nav-item" data-academic-tab="contatos" role="tab"><i class="fas fa-user-shield"></i><span>{{ __('Contatos') }}</span><small>{{ $person->contacts->count() }} {{ __('registros') }}</small></a>
        <a href="#section-documentos" class="sge-section-nav-item" data-academic-tab="documentos" role="tab"><i class="fas fa-file-signature"></i><span>{{ __('Documentos') }}</span><small>{{ $documents->count() }} {{ __('emitidos') }}</small></a>
        @if($canManagePerson)<a href="#section-auditoria" class="sge-section-nav-item" data-academic-tab="auditoria" role="tab"><i class="fas fa-history"></i><span>{{ __('Auditoria') }}</span><small>{{ __('movimentações') }}</small></a>@endif
    </nav>

    <div class="row">
        <div class="col-12">
            <section id="section-percurso" class="card shadow sge-panel-card mb-4" aria-labelledby="enrollments-title" data-academic-panel="percurso" role="tabpanel">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="enrollments-title">{{ __('Percurso na escola') }}</h2>
                        <p>{{ __('Matrículas, turmas, matrizes e documentos ligados a cada etapa cursada no Beabá.') }}</p>
                    </div>
                    <span class="badge badge-light">{{ $enrollments->count() }} {{ __('registro(s)') }}</span>
                </div>
                <div class="card-body">
                    <div class="sge-life-timeline">
                        @forelse ($enrollments as $enrollment)
                            <article class="sge-life-card {{ $enrollment->isActive() ? 'is-active' : '' }}">
                                <div class="sge-life-card-status">
                                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                    <span class="badge badge-{{ $enrollment->isActive() ? 'success' : 'secondary' }}">{{ __($enrollment->statusLabel()) }}</span>
                                </div>
                                <div class="sge-life-card-body">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <div>
                                            <h3>{{ $enrollment->schoolClass?->name ?? __('Turma não informada') }}</h3>
                                            <p>{{ $enrollment->schoolClass?->academicYear?->name }} · {{ $enrollment->schoolClass?->academicYear?->school?->name }}</p>
                                        </div>
                                        <strong class="sge-life-year">{{ $enrollment->enrolled_at?->format('Y') ?? __('Sem data') }}</strong>
                                    </div>
                                    <div class="sge-chip-list mt-2">
                                        @forelse ($enrollment->courses as $course)
                                            <span class="sge-info-chip"><strong>{{ $course->name }}</strong>{{ $course->stageLabel() ?? __('Matriz') }}</span>
                                        @empty
                                            <span class="sge-info-chip"><strong>{{ __('Matriz') }}</strong>{{ __('Não informada') }}</span>
                                        @endforelse
                                    </div>
                                    <div class="sge-student-dates mt-3">
                                        <span><strong>{{ __('Matrícula') }}</strong>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</span>
                                        <span><strong>{{ __('Resultado') }}</strong>{{ __($enrollment->finalResultLabel()) }}</span>
                                        @if ($enrollment->transferred_at)
                                            <span><strong>{{ __('Saída') }}</strong>{{ $enrollment->transferred_at->format('d/m/Y') }}</span>
                                        @endif
                                        @if ($enrollment->cancelled_at)
                                            <span><strong>{{ __('Cancelamento') }}</strong>{{ $enrollment->cancelled_at->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                    <div class="sge-action-row mt-3">
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('enrollments.report-card.show', $enrollment) }}">
                                            <i class="fas fa-chart-line mr-1" aria-hidden="true"></i>{{ __('Boletim') }}
                                        </a>
                                        @if ($canManagePerson)
                                            <a class="btn btn-sm btn-primary" href="{{ route('enrollments.documents', $enrollment) }}">
                                                <i class="fas fa-folder-open mr-1" aria-hidden="true"></i>{{ __('Documentos') }}
                                            </a>
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}">
                                                <i class="fas fa-file-alt mr-1" aria-hidden="true"></i>{{ __('Ficha individual') }}
                                            </a>
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.pdf', $enrollment) }}">
                                                <i class="fas fa-file-signature mr-1" aria-hidden="true"></i>{{ __('Matrícula') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="sge-empty-state"><i class="fas fa-user-graduate" aria-hidden="true"></i><p>{{ __('Nenhuma matrícula cadastrada.') }}</p></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section id="section-historicos" class="card shadow sge-panel-card mb-4" aria-labelledby="external-history-title" data-academic-panel="historicos" role="tabpanel">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="external-history-title">{{ __('Históricos recebidos') }}</h2>
                        <p>{{ __('Documentos trazidos de outras escolas, reclassificações e registros externos preservados pela secretaria.') }}</p>
                    </div>
                    @if ($canManagePerson)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.create', $person) }}">
                            <i class="fas fa-plus mr-1" aria-hidden="true"></i>{{ __('Novo') }}
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="sge-history-grid">
                        @forelse ($histories as $history)
                            <article class="sge-history-card">
                                <div>
                                    <span class="sge-page-kicker">{{ $history->stage ?: __('Histórico escolar') }}</span>
                                    <h3>{{ $history->title }}</h3>
                                    <p>{{ $history->school?->name ?? __('Sem escola vinculada') }}</p>
                                </div>
                                <div class="sge-history-card-meta">
                                    <span><strong>{{ $history->years->count() }}</strong> {{ __('coluna(s)') }}</span>
                                    <span><strong>{{ $history->components->count() }}</strong> {{ __('componente(s)') }}</span>
                                    <span class="badge badge-{{ $history->active ? 'success' : 'secondary' }}">{{ $history->active ? __('Ativo') : __('Inativo') }}</span>
                                </div>
                                @if ($canManagePerson)
                                    <div class="sge-action-row">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.show', [$person, $history]) }}">
                                            <i class="fas fa-eye mr-1" aria-hidden="true"></i>{{ __('Abrir') }}
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.pdf', [$person, $history]) }}">
                                            <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>PDF
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.edit', [$person, $history]) }}">
                                            <i class="fas fa-pen mr-1" aria-hidden="true"></i>{{ __('Editar') }}
                                        </a>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="sge-empty-state"><i class="fas fa-folder-open" aria-hidden="true"></i><p>{{ __('Nenhum histórico externo cadastrado.') }}</p></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section id="section-desempenho" class="card shadow sge-panel-card mb-4" aria-labelledby="results-title" data-academic-panel="desempenho" role="tabpanel">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="results-title">{{ __('Desempenho') }}</h2>
                        <p>{{ __('Resultados lançados, comportamento e convalidações parciais.') }}</p>
                    </div>
                    <div class="d-flex align-items-center flex-wrap justify-content-end">
                        @if ($canChooseScoreView)
                            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="{{ __('Forma de visualização das notas') }}">
                                <a class="btn btn-{{ $scoreView === 'numeros' ? 'primary' : 'outline-primary' }}" href="{{ route('people.student-map.show', ['person' => $person] + array_merge(request()->except('notas'), ['notas' => 'numeros'])) }}">{{ __('Números') }}</a>
                                <a class="btn btn-{{ $scoreView === 'conceitos' ? 'primary' : 'outline-primary' }}" href="{{ route('people.student-map.show', ['person' => $person] + array_merge(request()->except('notas'), ['notas' => 'conceitos'])) }}">{{ __('Conceitos') }}</a>
                            </div>
                        @endif
                        <span class="badge badge-light">{{ $assessmentResults->count() }} {{ __('lançamento(s)') }}</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($latestResults->isNotEmpty())
                        <div class="sge-result-strip mb-4" aria-label="{{ __('Últimos resultados lançados') }}">
                            @foreach ($latestResults as $result)
                                <article>
                                    <span>{{ $result->assessment?->component?->name ?? __('Componente') }}</span>
                                    <strong>{{ $scoreLabel($result) }}</strong>
                                    <small>{{ $result->assessment?->period?->name ?? __('Sem período') }}</small>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if ($convalidations->isNotEmpty())
                        <div class="sge-convalidation-strip mb-4">
                            @foreach ($convalidations->take(4) as $item)
                                @php($convalidation = $item['convalidation'])
                                <article>
                                    <i class="fas fa-file-import" aria-hidden="true"></i>
                                    <div>
                                        <strong>{{ $convalidation->component?->name ?? __('Componente') }}</strong>
                                        <span>{{ $convalidation->period?->name }} · {{ number_format((float) $convalidation->score, 1, ',', '.') }}</span>
                                        <small>{{ $convalidation->source_school ?: __('Escola de origem não informada') }}</small>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @forelse ($resultsByEnrollment as $group)
                        @php($enrollment = $group->first()->enrollment)
                        <details class="sge-student-details" @if($loop->first) open @endif>
                            <summary>
                                <span>
                                    <strong>{{ $enrollment?->schoolClass?->name ?? __('Matrícula sem turma') }}</strong>
                                    <small>{{ $enrollment?->schoolClass?->academicYear?->name }} · {{ $group->count() }} {{ __('resultado(s)') }}</small>
                                </span>
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <div class="sge-period-result-grid">
                                @foreach ($group->groupBy(fn ($result) => $result->assessment?->period?->name ?? __('Sem período')) as $periodName => $periodResults)
                                    @php($period = $periodResults->first()?->assessment?->period)
                                    @php($behaviorGrade = $behaviorByEnrollment->get($enrollment?->id, collect())->firstWhere('academic_period_id', $period?->id))
                                    <article class="sge-period-result-card">
                                        <header>
                                            <strong>{{ $periodName }}</strong>
                                            <span>{{ $periodResults->count() }} {{ __('lançamento(s)') }}</span>
                                        </header>
                                        <div class="sge-component-result">
                                            <div>
                                                <strong>{{ __('Comportamento') }}</strong>
                                                <span>{{ __('Lançamento da gestão') }}</span>
                                            </div>
                                            <div class="sge-grade-pills"><span>{{ $behaviorLabel($behaviorGrade) }}</span></div>
                                        </div>
                                        @foreach ($periodResults->groupBy(fn ($result) => $result->assessment?->component?->name ?? __('Componente não informado')) as $componentName => $componentResults)
                                            <div class="sge-component-result">
                                                <div>
                                                    <strong>{{ $componentName }}</strong>
                                                    <span>{{ $componentResults->first()->assessment?->component?->area?->name ?? __('Área não definida') }}</span>
                                                </div>
                                                <div class="sge-grade-pills">
                                                    @foreach ($componentResults as $result)
                                                        <span title="{{ $result->assessment?->title ?? __('Avaliação') }}">{{ $scoreLabel($result) }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <div class="sge-empty-state"><i class="fas fa-clipboard-list" aria-hidden="true"></i><p>{{ __('Nenhum resultado lançado até o momento.') }}</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-12">
            <section id="section-frequencia" class="card shadow sge-panel-card mb-4" aria-labelledby="attendance-title" data-academic-panel="frequencia" role="tabpanel">
                <div class="sge-panel-header"><div><h2 id="attendance-title">{{ __('Frequência') }}</h2><p>{{ __('Resumo por matrícula.') }}</p></div></div>
                <div class="card-body">
                    @forelse ($attendanceSummary as $summary)
                        @php($summaryTotal = max(1, (int) $summary['total']))
                        @php($summaryPresence = (int) $summary['present'] + (int) $summary['excused'])
                        @php($summaryRate = min(100, round(($summaryPresence / $summaryTotal) * 100, 1)))
                        <article class="sge-attendance-card">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <h3>{{ $summary['enrollment']?->schoolClass?->name ?? __('Turma não informada') }}</h3>
                                    <p>{{ $summary['enrollment']?->schoolClass?->academicYear?->name }}</p>
                                </div>
                                <strong>{{ number_format($summaryRate, 1, ',', '.') }}%</strong>
                            </div>
                            <div class="progress sge-attendance-progress" aria-hidden="true">
                                <div class="progress-bar" style="width: {{ $summaryRate }}%"></div>
                            </div>
                            <div class="sge-attendance-breakdown">
                                <span><strong>{{ $summary['present'] }}</strong>{{ __('presenças') }}</span>
                                <span><strong>{{ $summary['absent'] }}</strong>{{ __('faltas') }}</span>
                                <span><strong>{{ $summary['excused'] }}</strong>{{ __('justificadas') }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="sge-empty-state"><i class="fas fa-calendar-check" aria-hidden="true"></i><p>{{ __('Nenhuma chamada registrada.') }}</p></div>
                    @endforelse
                </div>
            </section>

            <section id="section-contatos" class="card shadow sge-panel-card mb-4" aria-labelledby="contacts-title" data-academic-panel="contatos" role="tabpanel">
                <div class="sge-panel-header"><div><h2 id="contacts-title">{{ __('Responsáveis e contatos') }}</h2><p>{{ __('Referências familiares e contatos de emergência.') }}</p></div></div>
                <div class="card-body">
                    @forelse ($person->contacts as $contact)
                        <article class="sge-side-card">
                            <div class="sge-side-card-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                            <div>
                                <strong>{{ $contact->name }}</strong>
                                <span>{{ $contact->label() }}</span>
                                <small>{{ collect([$contact->phone, $contact->secondary_phone, $contact->email])->filter()->join(' · ') ?: __('Sem contato informado') }}</small>
                            </div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">{{ __('Nenhum responsável ou contato cadastrado.') }}</p>
                    @endforelse
                </div>
            </section>

            <section id="section-documentos" class="card shadow sge-panel-card mb-4" aria-labelledby="documents-title" data-academic-panel="documentos" role="tabpanel">
                <div class="sge-panel-header"><div><h2 id="documents-title">{{ __('Documentos emitidos') }}</h2><p>{{ __('Códigos verificáveis associados ao estudante.') }}</p></div></div>
                <div class="card-body">
                    @forelse ($documents as $document)
                        @php($documentInfo = \App\Support\DocumentVerificationPresenter::make($document))
                        <article class="sge-side-card">
                            <div class="sge-side-card-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                            <div>
                                <strong>{{ $documentInfo['title'] ?? $documentInfo['type_label'] }}</strong>
                                <span>{{ $documentInfo['school_name'] ?? 'Centro Técnico Juvenil de Jarudore' }}</span>
                                <small>{{ $document->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</small>
                            </div>
                            <a class="btn btn-sm btn-outline-primary sge-icon-action ml-auto" href="{{ route('documents.verify', $document->verification_code) }}" aria-label="{{ __('Verificar documento') }} {{ $document->verification_code }}" title="{{ __('Verificar documento') }}">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </a>
                        </article>
                    @empty
                        <p class="text-muted mb-0">{{ __('Nenhum documento emitido para este estudante.') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($canManagePerson)
                <section id="section-auditoria" class="card shadow sge-panel-card mb-4" aria-labelledby="audit-title" data-academic-panel="auditoria" role="tabpanel">
                    <div class="sge-panel-header"><div><h2 id="audit-title">{{ __('Movimentações recentes') }}</h2><p>{{ __('Últimas alterações auditadas.') }}</p></div></div>
                    <div class="card-body">
                        @forelse ($auditLogs as $auditLog)
                            <article class="sge-timeline-item">
                                <strong>{{ \App\Support\AuditLogPresenter::actionLabel($auditLog->action) }}</strong>
                                <span>{{ \App\Support\AuditLogPresenter::modelLabel($auditLog->auditable_type) }}</span>
                                <small>{{ $auditLog->actorPerson?->full_name ?? __('Sistema') }} · {{ $auditLog->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</small>
                            </article>
                        @empty
                            <p class="text-muted mb-0">{{ __('Nenhuma alteração registrada.') }}</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
