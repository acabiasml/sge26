@extends('layouts.app')

@php
    $enrollment = $report['enrollment'];
    $student = $report['student'];
    $academicYear = $report['academicYear'];
    $schoolClass = $report['schoolClass'];
    $scoreLabel = function ($score, $date = null) use ($academicYear, $scoreView): string {
        if ($score === null || $score === '') {
            return '-';
        }

        if ($scoreView === 'conceitos') {
            $concept = $academicYear->school?->conceptForScore((float) $score, $date);

            return $concept?->shortLabel() ?? 'Conceito não definido';
        }

        return number_format((float) $score, 1, ',', '.');
    };
    $attendanceLabel = fn (?float $percentage): string => $percentage === null ? '-' : number_format($percentage, 1, ',', '.').'%';
    $componentStatus = function (array $summary) use ($report): string {
        if (($summary['complete_periods'] ?? 0) < ($summary['total_periods'] ?? 0)) {
            return 'Em acompanhamento';
        }

        return (float) $summary['points'] >= (float) $report['passingPoints'] ? 'Aprovado por pontos' : 'Abaixo dos pontos';
    };
    $friendlyConceptRange = function ($concept): string {
        $minimum = $concept->minimum_score !== null ? rtrim(rtrim(number_format((float) $concept->minimum_score, 1, ',', '.'), '0'), ',') : null;
        $maximum = $concept->maximum_score !== null ? rtrim(rtrim(number_format((float) $concept->maximum_score, 1, ',', '.'), '0'), ',') : null;

        if ($minimum === null && $maximum === null) {
            return 'para qualquer nota';
        }

        if ($minimum === null) {
            return ($concept->maximum_inclusive ? 'até ' : 'menor que ').$maximum;
        }

        if ($maximum === null) {
            return ($concept->minimum_inclusive ? 'a partir de ' : 'maior que ').$minimum;
        }

        return ($concept->minimum_inclusive ? 'de ' : 'maior que ')
            .$minimum
            .($concept->maximum_inclusive ? ' até ' : ' até menor que ')
            .$maximum;
    };
    $conceptLegend = $academicYear->school?->conceptsForDate($academicYear->ends_at ?? now()) ?? collect();
    $convalidations = $enrollment->periodConvalidations->sortByDesc('convalidated_at');
    $availableComponents = $report['courses']
        ->flatMap(fn ($course) => $course->components)
        ->unique('id')
        ->sortBy('name')
        ->values();
@endphp

@section('title', 'Boletim - '.$student->full_name)
@section('page-title', 'Boletim escolar')

@section('page-actions')
    @if ($canChooseScoreView)
        <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Forma de visualização das notas">
            <a class="btn btn-{{ $scoreView === 'numeros' ? 'primary' : 'outline-primary' }}" href="{{ route('enrollments.report-card.show', ['enrollment' => $enrollment, 'notas' => 'numeros']) }}">Números</a>
            <a class="btn btn-{{ $scoreView === 'conceitos' ? 'primary' : 'outline-primary' }}" href="{{ route('enrollments.report-card.show', ['enrollment' => $enrollment, 'notas' => 'conceitos']) }}">Conceitos</a>
        </div>
    @endif
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('enrollments.report-card.pdf', ['enrollment' => $enrollment, 'notas' => $scoreView]) }}" aria-label="Emitir boletim em PDF" title="Boletim em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('enrollments.individual-record.pdf', ['enrollment' => $enrollment, 'notas' => $scoreView]) }}" aria-label="Emitir ficha individual em PDF" title="Ficha individual em PDF">
        <i class="fas fa-file-alt" aria-hidden="true"></i>
    </a>
    @if (auth()->user()->canManageSchool($academicYear->school_id))
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('classes.enrollments.index', $schoolClass) }}" aria-label="Voltar às matrículas" title="Voltar às matrículas">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @else
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-diaries.index') }}" aria-label="Voltar ao meu diário" title="Voltar ao meu diário">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <section class="sge-student-profile mb-4" aria-labelledby="report-card-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($student->social_name ?: $student->full_name, 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">{{ $academicYear->school?->name }}</div>
                <h2 id="report-card-title">{{ $student->social_name ?: $student->full_name }}</h2>
                <div class="sge-student-meta">
                    <span><i class="fas fa-school" aria-hidden="true"></i>{{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $report['courses']) }}</span>
                    <span><i class="fas fa-calendar-alt" aria-hidden="true"></i>{{ $academicYear->name }}</span>
                    <span><i class="fas fa-layer-group" aria-hidden="true"></i>{{ $report['courses']->pluck('name')->join(' + ') ?: 'Matriz não informada' }}</span>
                </div>
            </div>
        </div>
        <div class="sge-student-profile-status">
            <span class="badge badge-light">Critérios</span>
            <strong>{{ number_format((float) $report['passingPoints'], 1, ',', '.') }}</strong>
            <span>pontos · {{ $report['minimumAttendance'] }}% frequência</span>
        </div>
    </section>

    <section class="sge-dashboard-metrics mb-4" aria-label="Resumo do boletim">
        <article class="sge-metric-card sge-metric-blue">
            <div class="sge-metric-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Componentes</span>
            <strong>{{ $report['annualComponents']->count() }}</strong>
            <span class="sge-metric-note">no boletim</span>
        </article>
        <article class="sge-metric-card sge-metric-green">
            <div class="sge-metric-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Frequência geral</span>
            <strong>{{ $attendanceLabel($report['annualAttendance']['percentage']) }}</strong>
            <span class="sge-metric-note">{{ $report['annualAttendance']['absent'] }} falta(s), {{ $report['annualAttendance']['justified'] }} justificada(s)</span>
        </article>
        <article class="sge-metric-card sge-metric-orange">
            <div class="sge-metric-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Períodos</span>
            <strong>{{ $report['periods']->count() }}</strong>
            <span class="sge-metric-note">avaliativos</span>
        </article>
        <article class="sge-metric-card sge-metric-brown">
            <div class="sge-metric-icon"><i class="fas fa-user-check" aria-hidden="true"></i></div>
            <span class="sge-metric-label">Visualização</span>
            <strong>{{ $scoreView === 'conceitos' ? 'Conceitos' : 'Notas' }}</strong>
            <span class="sge-metric-note">{{ $scoreView === 'conceitos' ? 'sem números para estudante' : 'uso interno' }}</span>
        </article>
    </section>

    @if($conceptLegend->isNotEmpty())
        <section class="card shadow sge-panel-card mb-4" aria-labelledby="concept-legend-title">
            <div class="sge-panel-header">
                <div>
                    <h2 id="concept-legend-title">Legenda dos conceitos</h2>
                    <p>Referência utilizada para converter notas numéricas em conceitos no boletim.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="sge-component-badges">
                    @foreach($conceptLegend as $concept)
                        <span class="badge badge-light">{{ $concept->shortLabel() }} = {{ $concept->name }} ({{ $friendlyConceptRange($concept) }})</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(auth()->user()->canManageSchool($academicYear->school_id))
        <section class="card shadow sge-panel-card mb-4" aria-labelledby="convalidation-title">
            <div class="sge-panel-header">
                <div>
                    <h2 id="convalidation-title">Convalidação de resultados parciais</h2>
                    <p>Use quando o estudante chega com resultados já cursados em outra escola.</p>
                </div>
                <span class="badge badge-light">{{ $convalidations->count() }} registro(s)</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('enrollments.convalidations.store', $enrollment) }}" class="mb-4">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="academic_period_id">Período</label>
                            <select id="academic_period_id" name="academic_period_id" class="form-control" required>
                                @foreach($report['periods'] as $period)
                                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="curriculum_component_id">Componente</label>
                            <select id="curriculum_component_id" name="curriculum_component_id" class="form-control" required>
                                @foreach($availableComponents as $component)
                                    <option value="{{ $component->id }}">{{ $component->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="score">Média</label>
                            <input id="score" name="score" data-mask="decimal" class="form-control" inputmode="decimal" required placeholder="7,0">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="convalidated_at">Data</label>
                            <input id="convalidated_at" name="convalidated_at" type="date" class="form-control" value="{{ now('America/Sao_Paulo')->toDateString() }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="attendance_lessons">Aulas cursadas na origem</label>
                            <input id="attendance_lessons" name="attendance_lessons" type="number" min="1" max="999" class="form-control" inputmode="numeric" placeholder="Opcional">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="attendance_absences">Faltas na origem</label>
                            <input id="attendance_absences" name="attendance_absences" type="number" min="0" max="999" class="form-control" inputmode="numeric" placeholder="0">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="attendance_justified_absences">Faltas justificadas na origem</label>
                            <input id="attendance_justified_absences" name="attendance_justified_absences" type="number" min="0" max="999" class="form-control" inputmode="numeric" placeholder="0">
                        </div>
                        <div class="form-group col-md-5">
                            <label for="source_school">Escola de origem</label>
                            <input id="source_school" name="source_school" class="form-control">
                        </div>
                        <div class="form-group col-md-7">
                            <label for="notes">Observações</label>
                            <input id="notes" name="notes" class="form-control" placeholder="Ex.: resultado apresentado em histórico parcial.">
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-check mr-1" aria-hidden="true"></i>Convalidar resultado
                    </button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Componente</th>
                                <th>Média</th>
                                <th>Frequência externa</th>
                                <th>Origem</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($convalidations as $convalidation)
                                <tr>
                                    <td>{{ $convalidation->period?->name }}</td>
                                    <td>{{ $convalidation->component?->name }}</td>
                                    <td>{{ number_format((float) $convalidation->score, 1, ',', '.') }}</td>
                                    <td>
                                        @if((int) ($convalidation->attendance_lessons ?? 0) > 0)
                                            {{ $convalidation->attendance_lessons }} aula(s), {{ (int) ($convalidation->attendance_absences ?? 0) }} falta(s)
                                            @if((int) ($convalidation->attendance_justified_absences ?? 0) > 0)
                                                <span class="d-block small text-muted">{{ $convalidation->attendance_justified_absences }} justificada(s)</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        {{ $convalidation->source_school ?: '-' }}
                                        @if($convalidation->notes)<span class="d-block small text-muted">{{ $convalidation->notes }}</span>@endif
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('enrollments.convalidations.destroy', [$enrollment, $convalidation]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover convalidação" title="Remover convalidação">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Nenhum resultado convalidado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <section class="card shadow sge-panel-card mb-4" aria-labelledby="annual-summary-title">
        <div class="sge-panel-header">
            <div>
                <h2 id="annual-summary-title">Resumo anual por componente</h2>
                <p>Soma dos pontos já calculados nos períodos e frequência efetiva, contando faltas justificadas como presença para aprovação.</p>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Componente</th>
                        <th>Área</th>
                        <th class="text-center">Pontos</th>
                        <th class="text-center">Frequência</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['annualComponents'] as $summary)
                        <tr>
                            <td>{{ $summary['component']->name }}</td>
                            <td>{{ $summary['component']->area?->name ?? 'Área não definida' }}</td>
                            <td class="text-center">{{ $scoreView === 'conceitos' ? '-' : number_format((float) $summary['points'], 1, ',', '.') }}</td>
                            <td class="text-center">{{ $attendanceLabel($summary['attendance']['percentage']) }}</td>
                            <td>{{ $componentStatus($summary) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhum componente no boletim.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach($report['periodReports'] as $periodReport)
        @php($period = $periodReport['period'])
        <section class="card shadow sge-panel-card mb-4" aria-labelledby="period-report-{{ $period->id }}">
            <div class="sge-panel-header">
                <div>
                    <h2 id="period-report-{{ $period->id }}">{{ $period->name }}</h2>
                    <p>{{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}</p>
                </div>
                <span class="badge badge-light">Comportamento: {{ $scoreLabel($periodReport['behavior']?->score, $period->ends_at ?? $period->starts_at) }}</span>
            </div>
            <div class="card-body">
                <div class="sge-period-result-grid">
                    @forelse($periodReport['components'] as $componentReport)
                        <article class="sge-period-result-card">
                            <header>
                                <strong>{{ $componentReport['component']->name }}</strong>
                                <span>{{ $componentReport['component']->area?->name ?? 'Área não definida' }}</span>
                            </header>
                            <div class="sge-component-result">
                                <div><strong>Média</strong><span>{{ $componentReport['average']['complete'] ? 'Completa' : $componentReport['average']['completed_assessments'].' de '.$componentReport['average']['total_assessments'].' lançada(s)' }}</span></div>
                                <div class="sge-grade-pills"><span>{{ $scoreLabel($componentReport['average']['value'], $period->ends_at ?? $period->starts_at) }}</span></div>
                            </div>
                            @if($componentReport['convalidation'])
                                <div class="sge-component-result">
                                    <div><strong>Convalidação</strong><span>{{ $componentReport['convalidation']->source_school ?: 'Resultado externo' }}</span></div>
                                    <div class="sge-grade-pills"><span>{{ number_format((float) $componentReport['convalidation']->score, 1, ',', '.') }}</span></div>
                                </div>
                            @endif
                            <div class="sge-component-result">
                                <div><strong>Frequência</strong><span>{{ $componentReport['attendance']['absent'] }} falta(s), {{ $componentReport['attendance']['justified'] }} justificada(s)</span></div>
                                <div class="sge-grade-pills"><span>{{ $attendanceLabel($componentReport['attendance']['percentage']) }}</span></div>
                            </div>
                            @foreach($componentReport['assessments'] as $assessment)
                                @php($result = $assessment->results->first())
                                <div class="sge-component-result">
                                    <div><strong>{{ $assessment->title }}</strong><span>{{ $assessment->is_recovery ? 'Recuperação' : 'Avaliação' }}</span></div>
                                    <div class="sge-grade-pills"><span>{{ $scoreLabel($result?->score, $period->ends_at ?? $period->starts_at) }}</span></div>
                                </div>
                            @endforeach
                        </article>
                    @empty
                        <div class="sge-empty-state"><i class="fas fa-clipboard-list" aria-hidden="true"></i><p>Nenhum componente ativo neste período.</p></div>
                    @endforelse
                </div>
            </div>
        </section>
    @endforeach
@endsection
