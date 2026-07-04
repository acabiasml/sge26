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
            return 'Pendente';
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

@section('title', 'Vida escolar')
@section('page-title', 'Vida escolar')

@section('page-actions')
    @if ($canManagePerson)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.create', $person) }}" aria-label="Cadastrar histórico escolar de {{ $person->full_name }}" title="Novo histórico escolar">
            <i class="fas fa-folder-plus" aria-hidden="true"></i>
        </a>
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="Voltar ao cadastro de {{ $person->full_name }}" title="Voltar ao cadastro">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @endif
    @if ($canManagePerson)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="Emitir ficha cadastral em PDF de {{ $person->full_name }}" title="Ficha cadastral em PDF">
            <i class="fas fa-file-pdf" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <section class="sge-student-profile sge-life-hero mb-4" aria-labelledby="student-life-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">Vida escolar</div>
                <h2 id="student-life-title">{{ $person->social_name ?: $person->full_name }}</h2>
                <div class="sge-student-meta">
                    <span><i class="fas fa-envelope" aria-hidden="true"></i>{{ $person->institutional_email ?: 'E-mail institucional não informado' }}</span>
                    @if ($person->student_inep)
                        <span><i class="fas fa-id-card" aria-hidden="true"></i>INEP {{ $person->student_inep }}</span>
                    @endif
                    <span><i class="fas fa-user-check" aria-hidden="true"></i>{{ $person->active ? 'Cadastro ativo' : 'Cadastro inativo' }}</span>
                </div>
            </div>
        </div>
        <div class="sge-life-actions">
            @if ($activeEnrollments->first())
                <a class="btn btn-sm btn-primary" href="{{ route('enrollments.report-card.show', $activeEnrollments->first()) }}">
                    <i class="fas fa-chart-line mr-1" aria-hidden="true"></i>Boletim atual
                </a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.individual-record.pdf', $activeEnrollments->first()) }}">
                    <i class="fas fa-file-alt mr-1" aria-hidden="true"></i>Ficha individual
                </a>
            @endif
            @if ($canManagePerson)
                <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.create', $person) }}">
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>Histórico recebido
                </a>
            @endif
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="Resumo da vida escolar">
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Matrículas ativas</span>
            <strong>{{ $activeEnrollments->count() }}</strong>
            <span class="sge-metric-note">{{ $enrollments->count() }} matrícula(s) no percurso</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Resultados</span>
            <strong>{{ $assessmentResults->count() }}</strong>
            <span class="sge-metric-note">{{ $convalidations->count() }} convalidação(ões)</span>
        </article>
        <article class="sge-metric-card sge-metric-orange">
            <div class="sge-metric-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Frequência</span>
            <strong>{{ $attendanceRate !== null ? number_format($attendanceRate, 1, ',', '.') . '%' : '-' }}</strong>
            <span class="sge-metric-note">{{ $absenceCount }} falta(s) registrada(s)</span>
        </article>
        <article class="sge-metric-card sge-metric-brown">
            <div class="sge-metric-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Históricos</span>
            <strong>{{ $histories->count() }}</strong>
            <span class="sge-metric-note">documento(s) recebido(s)</span>
        </article>
    </section>

    <div class="row">
        <div class="col-xl-8">
            <section class="card shadow sge-panel-card mb-4" aria-labelledby="enrollments-title">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="enrollments-title">Percurso na escola</h2>
                        <p>Matrículas, turmas, matrizes e documentos ligados a cada etapa cursada no Beabá.</p>
                    </div>
                    <span class="badge badge-light">{{ $enrollments->count() }} registro(s)</span>
                </div>
                <div class="card-body">
                    <div class="sge-life-timeline">
                        @forelse ($enrollments as $enrollment)
                            <article class="sge-life-card {{ $enrollment->isActive() ? 'is-active' : '' }}">
                                <div class="sge-life-card-status">
                                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                    <span class="badge badge-{{ $enrollment->isActive() ? 'success' : 'secondary' }}">{{ $enrollment->statusLabel() }}</span>
                                </div>
                                <div class="sge-life-card-body">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <div>
                                            <h3>{{ $enrollment->schoolClass?->name ?? 'Turma não informada' }}</h3>
                                            <p>{{ $enrollment->schoolClass?->academicYear?->name }} · {{ $enrollment->schoolClass?->academicYear?->school?->name }}</p>
                                        </div>
                                        <strong class="sge-life-year">{{ $enrollment->enrolled_at?->format('Y') ?? 'Sem data' }}</strong>
                                    </div>
                                    <div class="sge-chip-list mt-2">
                                        @forelse ($enrollment->courses as $course)
                                            <span class="sge-info-chip"><strong>{{ $course->name }}</strong>{{ $course->stageLabel() ?? 'Matriz' }}</span>
                                        @empty
                                            <span class="sge-info-chip"><strong>Matriz</strong>Não informada</span>
                                        @endforelse
                                    </div>
                                    <div class="sge-student-dates mt-3">
                                        <span><strong>Matrícula</strong>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</span>
                                        <span><strong>Resultado</strong>{{ $enrollment->finalResultLabel() }}</span>
                                        @if ($enrollment->transferred_at)
                                            <span><strong>Saída</strong>{{ $enrollment->transferred_at->format('d/m/Y') }}</span>
                                        @endif
                                        @if ($enrollment->cancelled_at)
                                            <span><strong>Cancelamento</strong>{{ $enrollment->cancelled_at->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                    <div class="sge-action-row mt-3">
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('enrollments.report-card.show', $enrollment) }}">
                                            <i class="fas fa-chart-line mr-1" aria-hidden="true"></i>Boletim
                                        </a>
                                        <a class="btn btn-sm btn-primary" href="{{ route('enrollments.documents', $enrollment) }}">
                                            <i class="fas fa-folder-open mr-1" aria-hidden="true"></i>Documentos
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}">
                                            <i class="fas fa-file-alt mr-1" aria-hidden="true"></i>Ficha individual
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('enrollments.pdf', $enrollment) }}">
                                            <i class="fas fa-file-signature mr-1" aria-hidden="true"></i>Matrícula
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="sge-empty-state"><i class="fas fa-user-graduate" aria-hidden="true"></i><p>Nenhuma matrícula cadastrada.</p></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4" aria-labelledby="external-history-title">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="external-history-title">Históricos recebidos</h2>
                        <p>Documentos trazidos de outras escolas, reclassificações e registros externos preservados pela secretaria.</p>
                    </div>
                    @if ($canManagePerson)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.create', $person) }}">
                            <i class="fas fa-plus mr-1" aria-hidden="true"></i>Novo
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="sge-history-grid">
                        @forelse ($histories as $history)
                            <article class="sge-history-card">
                                <div>
                                    <span class="sge-page-kicker">{{ $history->stage ?: 'Histórico escolar' }}</span>
                                    <h3>{{ $history->title }}</h3>
                                    <p>{{ $history->school?->name ?? 'Sem escola vinculada' }}</p>
                                </div>
                                <div class="sge-history-card-meta">
                                    <span><strong>{{ $history->years->count() }}</strong> coluna(s)</span>
                                    <span><strong>{{ $history->components->count() }}</strong> componente(s)</span>
                                    <span class="badge badge-{{ $history->active ? 'success' : 'secondary' }}">{{ $history->active ? 'Ativo' : 'Inativo' }}</span>
                                </div>
                                <div class="sge-action-row">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.show', [$person, $history]) }}">
                                        <i class="fas fa-eye mr-1" aria-hidden="true"></i>Abrir
                                    </a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.pdf', [$person, $history]) }}">
                                        <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>PDF
                                    </a>
                                    @if ($canManagePerson)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.edit', [$person, $history]) }}">
                                            <i class="fas fa-pen mr-1" aria-hidden="true"></i>Editar
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="sge-empty-state"><i class="fas fa-folder-open" aria-hidden="true"></i><p>Nenhum histórico externo cadastrado.</p></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4" aria-labelledby="results-title">
                <div class="sge-panel-header">
                    <div>
                        <h2 id="results-title">Desempenho</h2>
                        <p>Resultados lançados, comportamento e convalidações parciais.</p>
                    </div>
                    <div class="d-flex align-items-center flex-wrap justify-content-end">
                        @if ($canChooseScoreView)
                            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Forma de visualização das notas">
                                <a class="btn btn-{{ $scoreView === 'numeros' ? 'primary' : 'outline-primary' }}" href="{{ route('people.student-map.show', ['person' => $person] + array_merge(request()->except('notas'), ['notas' => 'numeros'])) }}">Números</a>
                                <a class="btn btn-{{ $scoreView === 'conceitos' ? 'primary' : 'outline-primary' }}" href="{{ route('people.student-map.show', ['person' => $person] + array_merge(request()->except('notas'), ['notas' => 'conceitos'])) }}">Conceitos</a>
                            </div>
                        @endif
                        <span class="badge badge-light">{{ $assessmentResults->count() }} lançamento(s)</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($latestResults->isNotEmpty())
                        <div class="sge-result-strip mb-4" aria-label="Últimos resultados lançados">
                            @foreach ($latestResults as $result)
                                <article>
                                    <span>{{ $result->assessment?->component?->name ?? 'Componente' }}</span>
                                    <strong>{{ $scoreLabel($result) }}</strong>
                                    <small>{{ $result->assessment?->period?->name ?? 'Sem período' }}</small>
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
                                        <strong>{{ $convalidation->component?->name ?? 'Componente' }}</strong>
                                        <span>{{ $convalidation->period?->name }} · {{ number_format((float) $convalidation->score, 1, ',', '.') }}</span>
                                        <small>{{ $convalidation->source_school ?: 'Escola de origem não informada' }}</small>
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
                                    <strong>{{ $enrollment?->schoolClass?->name ?? 'Matrícula sem turma' }}</strong>
                                    <small>{{ $enrollment?->schoolClass?->academicYear?->name }} · {{ $group->count() }} resultado(s)</small>
                                </span>
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <div class="sge-period-result-grid">
                                @foreach ($group->groupBy(fn ($result) => $result->assessment?->period?->name ?? 'Sem período') as $periodName => $periodResults)
                                    @php($period = $periodResults->first()?->assessment?->period)
                                    @php($behaviorGrade = $behaviorByEnrollment->get($enrollment?->id, collect())->firstWhere('academic_period_id', $period?->id))
                                    <article class="sge-period-result-card">
                                        <header>
                                            <strong>{{ $periodName }}</strong>
                                            <span>{{ $periodResults->count() }} lançamento(s)</span>
                                        </header>
                                        <div class="sge-component-result">
                                            <div>
                                                <strong>Comportamento</strong>
                                                <span>Lançamento da gestão</span>
                                            </div>
                                            <div class="sge-grade-pills"><span>{{ $behaviorLabel($behaviorGrade) }}</span></div>
                                        </div>
                                        @foreach ($periodResults->groupBy(fn ($result) => $result->assessment?->component?->name ?? 'Componente não informado') as $componentName => $componentResults)
                                            <div class="sge-component-result">
                                                <div>
                                                    <strong>{{ $componentName }}</strong>
                                                    <span>{{ $componentResults->first()->assessment?->component?->area?->name ?? 'Área não definida' }}</span>
                                                </div>
                                                <div class="sge-grade-pills">
                                                    @foreach ($componentResults as $result)
                                                        <span title="{{ $result->assessment?->title ?? 'Avaliação' }}">{{ $scoreLabel($result) }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <div class="sge-empty-state"><i class="fas fa-clipboard-list" aria-hidden="true"></i><p>Nenhum resultado lançado até o momento.</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card shadow sge-panel-card mb-4" aria-labelledby="attendance-title">
                <div class="sge-panel-header"><div><h2 id="attendance-title">Frequência</h2><p>Resumo por matrícula.</p></div></div>
                <div class="card-body">
                    @forelse ($attendanceSummary as $summary)
                        @php($summaryTotal = max(1, (int) $summary['total']))
                        @php($summaryPresence = (int) $summary['present'] + (int) $summary['excused'])
                        @php($summaryRate = min(100, round(($summaryPresence / $summaryTotal) * 100, 1)))
                        <article class="sge-attendance-card">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <h3>{{ $summary['enrollment']?->schoolClass?->name ?? 'Turma não informada' }}</h3>
                                    <p>{{ $summary['enrollment']?->schoolClass?->academicYear?->name }}</p>
                                </div>
                                <strong>{{ number_format($summaryRate, 1, ',', '.') }}%</strong>
                            </div>
                            <div class="progress sge-attendance-progress" aria-hidden="true">
                                <div class="progress-bar" style="width: {{ $summaryRate }}%"></div>
                            </div>
                            <div class="sge-attendance-breakdown">
                                <span><strong>{{ $summary['present'] }}</strong>presenças</span>
                                <span><strong>{{ $summary['absent'] }}</strong>faltas</span>
                                <span><strong>{{ $summary['excused'] }}</strong>justificadas</span>
                            </div>
                        </article>
                    @empty
                        <div class="sge-empty-state"><i class="fas fa-calendar-check" aria-hidden="true"></i><p>Nenhuma chamada registrada.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4" aria-labelledby="contacts-title">
                <div class="sge-panel-header"><div><h2 id="contacts-title">Responsáveis e contatos</h2><p>Referências familiares e contatos de emergência.</p></div></div>
                <div class="card-body">
                    @forelse ($person->contacts as $contact)
                        <article class="sge-side-card">
                            <div class="sge-side-card-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                            <div>
                                <strong>{{ $contact->name }}</strong>
                                <span>{{ $contact->label() }}</span>
                                <small>{{ collect([$contact->phone, $contact->secondary_phone, $contact->email])->filter()->join(' · ') ?: 'Sem contato informado' }}</small>
                            </div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhum responsável ou contato cadastrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="card shadow sge-panel-card mb-4" aria-labelledby="documents-title">
                <div class="sge-panel-header"><div><h2 id="documents-title">Documentos emitidos</h2><p>Códigos verificáveis associados ao estudante.</p></div></div>
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
                            <a class="btn btn-sm btn-outline-primary sge-icon-action ml-auto" href="{{ route('documents.verify', $document->verification_code) }}" aria-label="Verificar documento {{ $document->verification_code }}" title="Verificar documento">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </a>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhum documento emitido para este estudante.</p>
                    @endforelse
                </div>
            </section>

            @if ($canManagePerson)
                <section class="card shadow sge-panel-card mb-4" aria-labelledby="audit-title">
                    <div class="sge-panel-header"><div><h2 id="audit-title">Movimentações recentes</h2><p>Últimas alterações auditadas.</p></div></div>
                    <div class="card-body">
                        @forelse ($auditLogs as $auditLog)
                            <article class="sge-timeline-item">
                                <strong>{{ \App\Support\AuditLogPresenter::actionLabel($auditLog->action) }}</strong>
                                <span>{{ \App\Support\AuditLogPresenter::modelLabel($auditLog->auditable_type) }}</span>
                                <small>{{ $auditLog->actorPerson?->full_name ?? 'Sistema' }} · {{ $auditLog->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</small>
                            </article>
                        @empty
                            <p class="text-muted mb-0">Nenhuma alteração registrada.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
