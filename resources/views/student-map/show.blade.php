@extends('layouts.app')

@php
    $activeEnrollments = $enrollments->filter->isActive();
    $absenceCount = $attendanceSummary->sum('absent');
@endphp

@section('title', 'Mapa do estudante')
@section('page-title', 'Mapa do estudante')

@section('page-actions')
    @if ($canManagePerson)
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="Voltar ao cadastro de {{ $person->full_name }}" title="Voltar ao cadastro">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @endif
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="Emitir ficha em PDF de {{ $person->full_name }}" title="Ficha em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="sge-student-map-hero mb-4" aria-labelledby="student-map-title">
        <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->social_name ?: $person->full_name, 0, 1) }}</div>
        <div>
            <div class="sge-page-kicker">Acompanhamento escolar</div>
            <h2 id="student-map-title" class="h3 mb-1">{{ $person->social_name ?: $person->full_name }}</h2>
            <p class="mb-0 text-muted">
                {{ $person->institutional_email ?: 'E-mail institucional não informado' }}
                @if ($person->student_inep)
                    <span class="ml-2">INEP: {{ $person->student_inep }}</span>
                @endif
            </p>
        </div>
        <div class="sge-map-status ml-auto">
            <span class="badge badge-{{ $person->active ? 'success' : 'secondary' }}">Cadastro {{ $person->active ? 'ativo' : 'inativo' }}</span>
            <span class="small text-muted d-block mt-2">{{ $activeEnrollments->count() }} matrícula(s) ativa(s)</span>
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="Resumo acadêmico">
        <article class="sge-metric-card">
            <div class="sge-metric-icon sge-metric-icon-blue"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
            <div><span>Matrículas ativas</span><strong>{{ $activeEnrollments->count() }}</strong></div>
        </article>
        <article class="sge-metric-card">
            <div class="sge-metric-icon sge-metric-icon-green"><i class="fas fa-clipboard-check" aria-hidden="true"></i></div>
            <div><span>Resultados lançados</span><strong>{{ $assessmentResults->count() }}</strong></div>
        </article>
        <article class="sge-metric-card">
            <div class="sge-metric-icon sge-metric-icon-orange"><i class="fas fa-calendar-times" aria-hidden="true"></i></div>
            <div><span>Faltas registradas</span><strong>{{ $absenceCount }}</strong></div>
        </article>
        <article class="sge-metric-card">
            <div class="sge-metric-icon sge-metric-icon-brown"><i class="fas fa-file-alt" aria-hidden="true"></i></div>
            <div><span>Documentos emitidos</span><strong>{{ $documents->count() }}</strong></div>
        </article>
    </section>

    <div class="row">
        <div class="col-xl-8">
            <section class="card shadow mb-4" aria-labelledby="enrollments-title">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h2 id="enrollments-title" class="h6 m-0 font-weight-bold text-primary">Matrículas e percurso</h2>
                    <span class="small text-muted">{{ $enrollments->count() }} registro(s)</span>
                </div>
                <div class="card-body">
                    @forelse ($enrollments as $enrollment)
                        <article class="sge-enrollment-card">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div class="mr-3">
                                    <h3 class="h6 mb-1">{{ $enrollment->schoolClass?->academicYear?->name }} · {{ $enrollment->schoolClass?->name }}</h3>
                                    <p class="small text-muted mb-1">{{ $enrollment->schoolClass?->academicYear?->school?->name }}</p>
                                    <p class="mb-0">{{ $enrollment->courses->pluck('name')->join(' + ') ?: 'Matriz não informada' }}</p>
                                </div>
                                <span class="badge badge-{{ $enrollment->isActive() ? 'success' : 'secondary' }}">{{ $enrollment->statusLabel() }}</span>
                            </div>
                            <div class="small text-muted mt-2">
                                Matrícula: {{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}
                                @if ($enrollment->transferred_at) · Saída: {{ $enrollment->transferred_at->format('d/m/Y') }} @endif
                                @if ($enrollment->cancelled_at) · Cancelamento: {{ $enrollment->cancelled_at->format('d/m/Y') }} @endif
                            </div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhuma matrícula cadastrada.</p>
                    @endforelse
                </div>
            </section>

            <section class="card shadow mb-4" aria-labelledby="results-title">
                <div class="card-header py-3">
                    <h2 id="results-title" class="h6 m-0 font-weight-bold text-primary">Resultados das avaliações</h2>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Componente curricular</th>
                                <th>Avaliação</th>
                                <th class="text-right">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assessmentResults as $result)
                                <tr>
                                    <td>
                                        {{ $result->assessment?->period?->name ?? 'Sem período' }}
                                        <span class="d-block small text-muted">{{ $result->enrollment?->schoolClass?->name }}</span>
                                    </td>
                                    <td>
                                        {{ $result->assessment?->component?->name ?? '-' }}
                                        <span class="d-block small text-muted">{{ $result->assessment?->component?->area?->name ?? 'Área não definida' }}</span>
                                    </td>
                                    <td>
                                        {{ $result->assessment?->title ?? '-' }}
                                        <span class="d-block small text-muted">{{ $result->assessment?->assessment_date?->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="text-right">
                                        {{ number_format((float) $result->score, 2, ',', '.') }}
                                        <span class="small text-muted">/ {{ number_format((float) $result->assessment?->maximum_score, 2, ',', '.') }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Nenhum resultado lançado até o momento.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card shadow mb-4" aria-labelledby="attendance-title">
                <div class="card-header py-3">
                    <h2 id="attendance-title" class="h6 m-0 font-weight-bold text-primary">Resumo de frequência</h2>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th class="text-right">Presenças</th>
                                <th class="text-right">Faltas</th>
                                <th class="text-right">Justificadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attendanceSummary as $summary)
                                <tr>
                                    <td>
                                        {{ $summary['enrollment']?->schoolClass?->name ?? '-' }}
                                        <span class="d-block small text-muted">{{ $summary['enrollment']?->schoolClass?->academicYear?->name }}</span>
                                    </td>
                                    <td class="text-right">{{ $summary['present'] }}</td>
                                    <td class="text-right">{{ $summary['absent'] }}</td>
                                    <td class="text-right">{{ $summary['excused'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Nenhuma chamada registrada até o momento.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card shadow mb-4" aria-labelledby="contacts-title">
                <div class="card-header py-3"><h2 id="contacts-title" class="h6 m-0 font-weight-bold text-primary">Responsáveis e contatos</h2></div>
                <div class="card-body">
                    @forelse ($person->contacts as $contact)
                        <article class="sge-mini-row">
                            <div><strong>{{ $contact->name }}</strong><span>{{ $contact->label() }}</span></div>
                            <div class="small text-muted text-right">{{ collect([$contact->phone, $contact->secondary_phone, $contact->email])->filter()->join(' · ') ?: 'Sem contato' }}</div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhum responsável ou contato cadastrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="card shadow mb-4" aria-labelledby="documents-title">
                <div class="card-header py-3"><h2 id="documents-title" class="h6 m-0 font-weight-bold text-primary">Documentos emitidos</h2></div>
                <div class="card-body">
                    @forelse ($documents as $document)
                        @php($documentInfo = \App\Support\DocumentVerificationPresenter::make($document))
                        <article class="sge-mini-row">
                            <div>
                                <strong>{{ $documentInfo['title'] ?? $documentInfo['type_label'] }}</strong>
                                <span>{{ $documentInfo['school_name'] ?? 'Centro Técnico Juvenil de Jarudore' }}</span>
                            </div>
                            <div class="small text-right">
                                <a href="{{ route('documents.verify', $document->verification_code) }}">Verificar</a>
                                <span class="d-block text-muted">{{ $document->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y') }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhum documento emitido para este estudante.</p>
                    @endforelse
                </div>
            </section>

            @if ($canManagePerson)
                <section class="card shadow mb-4" aria-labelledby="history-title">
                    <div class="card-header py-3"><h2 id="history-title" class="h6 m-0 font-weight-bold text-primary">Histórico recente</h2></div>
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
