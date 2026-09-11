@extends('layouts.app')

@section('title', __('Diário - ').$component->name)
@section('page-title', __('Diário: ').$component->name)

@section('page-actions')
    @if($period)
        <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$schoolClass, $component, 'period' => $period->id]) }}" aria-label="{{ __('Imprimir diário do período em PDF') }}" title="{{ __('Diário do período') }}"><i class="fas fa-file-pdf" aria-hidden="true"></i></a>
        <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$schoolClass, $component, 'period' => $period->id, 'notas' => 'conceitos']) }}" aria-label="{{ __('Imprimir diário do período em conceitos') }}" title="{{ __('Período em conceitos') }}"><i class="fas fa-star-half-alt" aria-hidden="true"></i></a>
    @endif
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$schoolClass, $component]) }}" aria-label="{{ __('Imprimir diário completo do ano em PDF') }}" title="{{ __('Diário anual') }}"><i class="fas fa-calendar-check" aria-hidden="true"></i></a>
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$schoolClass, $component, 'notas' => 'conceitos']) }}" aria-label="{{ __('Imprimir diário completo do ano em conceitos') }}" title="{{ __('Ano em conceitos') }}"><i class="fas fa-award" aria-hidden="true"></i></a>
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$academicYear, $schoolClass]) }}" aria-label="{{ __('Imprimir horário da turma') }}" title="{{ __('Imprimir horário da turma') }}">
        <i class="fas fa-calendar-week" aria-hidden="true"></i>
    </a>
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$schoolClass, $component]) }}" aria-label="{{ __('Imprimir lista de chamada mensal') }}" title="{{ __('Imprimir lista de chamada mensal') }}">
        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.index') }}" aria-label="{{ __('Voltar aos diários') }}" title="{{ __('Voltar aos diários') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas do diário') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() && $period ? 'notas' : 'resumo' }}">
        <a href="#section-resumo" class="sge-section-nav-item" data-academic-tab="resumo" role="tab"><i class="fas fa-clipboard-list"></i><span>{{ __('Resumo') }}</span><small>{{ __('turma e período') }}</small></a>
        @if($period)
            <a href="#section-situacao" class="sge-section-nav-item" data-academic-tab="situacao" role="tab"><i class="fas fa-check-circle"></i><span>{{ __('Situação') }}</span><small>{{ $confirmation?->confirmed ? __('confirmado') : __('em lançamento') }}</small></a>
            <a href="#section-frequencia" class="sge-section-nav-item" data-academic-tab="frequencia" role="tab"><i class="fas fa-clipboard-check"></i><span>{{ __('Frequência') }}</span><small>{{ $attendanceRecords->count() }} {{ __('chamadas') }}</small></a>
            <a href="#section-notas" class="sge-section-nav-item" data-academic-tab="notas" role="tab"><i class="fas fa-star-half-alt"></i><span>{{ __('Notas') }}</span><small>{{ $assessmentRules->count() }} {{ __('avaliações') }}</small></a>
        @endif
    </nav>

    <div class="row" id="section-resumo" data-academic-panel="resumo" role="tabpanel">
        <div class="col-lg-7 mb-4">
            <section class="card shadow" aria-labelledby="diary-summary-title">
                <div class="card-header py-3"><h2 id="diary-summary-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Resumo') }}</h2></div>
                <div class="card-body">
                    <dl class="mb-0 sge-academic-summary-details">
                        <dt>{{ __('Escola') }}</dt><dd>{{ $academicYear->school?->name }}</dd>
                        <dt>{{ __('Ano letivo') }}</dt><dd>{{ $academicYear->name }}</dd>
                        <dt>{{ __('Turma') }}</dt><dd>{{ $schoolClass->name }}</dd>
                        <dt>{{ __('Turno') }}</dt><dd>{{ __($schoolClass->shift ?: 'Não definido') }}</dd>
                        <dt>{{ __('Matriz') }}</dt><dd>{{ $course->name }}</dd>
                        <dt>{{ __('Área') }}</dt><dd>{{ $component->area?->name ?? __('Não definida') }}</dd>
                        <dt>{{ __('Docência titular') }}</dt><dd>{{ $assignment->teacher?->full_name ?? __('Não definida') }}</dd>
                        <dt>{{ __('Vigência do componente') }}</dt><dd>{{ $component->startsPeriod?->name ?? __('início da turma') }} {{ __('até') }} {{ $component->endsPeriod?->name ?? __('fim da turma') }}</dd>
                        <dt>{{ __('Carga horária prevista') }}</dt><dd>{{ $component->formattedCalculatedWorkloadHours($course) }} {{ __('horas') }}</dd>
                        <dt>{{ __('Período atual') }}</dt><dd>{{ $period ? $period->name.' · '.$period->starts_at->format('d/m/Y').__(' a ').$period->ends_at->format('d/m/Y') : __('Não selecionado') }}</dd>
                        <dt>{{ __('Situação do período') }}</dt><dd>{{ $period ? ($confirmation?->confirmed ? __('Confirmado') : __('Em lançamento')) : __('Sem período') }}</dd>
                    </dl>
                    <div class="sge-academic-metrics mt-3 mb-0" aria-label="{{ __('Indicadores do diário no período selecionado') }}">
                        <div><strong>{{ $enrollments->filter->isActive()->count() }}</strong><span>{{ __('estudantes ativos') }}</span></div>
                        <div><strong>{{ $attendanceRecords->count() }}</strong><span>{{ __('chamadas') }}</span></div>
                        <div><strong>{{ $contents->count() }}</strong><span>{{ __('conteúdos') }}</span></div>
                        <div><strong>{{ $assessments->count() }}</strong><span>{{ __('avaliações') }}</span></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-5 mb-4">
            <section class="card shadow" aria-labelledby="period-title">
                <div class="card-header py-3"><h2 id="period-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Período avaliativo') }}</h2></div>
                <div class="card-body">
                    @if ($periods->isNotEmpty())
                        <div class="btn-group flex-wrap" role="group" aria-label="{{ __('Períodos avaliativos') }}">
                            @foreach ($periods as $availablePeriod)
                                <a class="btn btn-sm {{ $period?->id === $availablePeriod->id ? 'btn-primary' : 'btn-outline-primary' }} mb-2" href="{{ route('teacher-diaries.show', [$schoolClass, $component, 'period' => $availablePeriod->id]) }}">{{ $availablePeriod->name }}</a>
                            @endforeach
                        </div>
                    @else
                        <p class="mb-0 text-muted">{{ __('Este ano letivo ainda não possui períodos avaliativos cadastrados.') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    @if ($period)
        <div id="section-situacao" data-academic-panel="situacao" role="tabpanel">
        @if(($alerts ?? collect())->isNotEmpty())
            <section class="card shadow mb-4 border-left-warning" aria-labelledby="diary-alerts-title">
                <div class="card-header py-3">
                    <h2 id="diary-alerts-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Alertas da gestão') }}</h2>
                </div>
                <div class="card-body">
                    @foreach($alerts as $alert)
                        <div class="alert alert-warning mb-2 d-flex align-items-start justify-content-between flex-wrap">
                            <div class="mr-3">
                                <strong>{{ $alert->fromPerson?->full_name ?? __('Gestão') }}</strong>
                                <span class="small text-muted">· {{ $alert->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                                <p class="mb-0 mt-1">{{ $alert->message }}</p>
                            </div>
                            @if($alert->to_person_id === auth()->user()->person_id)
                                <form method="POST" action="{{ route('teacher-diaries.alerts.dismiss', $alert) }}" class="mt-2 mt-md-0">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-warning" type="submit">
                                        <i class="fas fa-check mr-1" aria-hidden="true"></i>{{ __('Dispensar') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @php($canConfirmPeriod = now()->startOfDay()->gte($period->ends_at->copy()->startOfDay()))
        <section class="card shadow mb-4 sge-diary-closing" aria-labelledby="closing-title">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap"><h2 id="closing-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Situação do período') }}</h2><span class="badge badge-{{ $confirmation?->confirmed ? 'success' : 'warning' }}">{{ $confirmation?->confirmed ? __('Confirmado') : __('Em lançamento') }}</span></div>
            <div class="card-body">
                @if ($confirmation?->confirmed)
                    <p class="mb-2"><i class="fas fa-check-circle text-success mr-1" aria-hidden="true"></i>{{ __('Confirmado em') }} {{ $confirmation->confirmed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} {{ __('por') }} {{ $confirmation->confirmedBy?->full_name ?? __('usuário não identificado') }}.</p>
                    @if ($canManageDiary)
                        <details><summary class="small font-weight-bold">{{ __('Reabrir período para ajustes') }}</summary><form method="POST" action="{{ route('teacher-diaries.confirmation.reopen', [$schoolClass, $component]) }}" class="mt-2">@csrf<input type="hidden" name="academic_period_id" value="{{ $period->id }}"><label class="sr-only" for="reopen_reason">{{ __('Motivo da reabertura') }}</label><div class="input-group"><input id="reopen_reason" name="reopen_reason" class="form-control" placeholder="{{ __('Motivo da reabertura') }}" required><div class="input-group-append"><button class="btn btn-outline-warning" type="submit">{{ __('Reabrir') }}</button></div></div></form></details>
                    @endif
                @else
                    <div class="sge-diary-pending-grid">
                        <div><strong>{{ count($diaryPending['attendance_without_content']) }}</strong><span>{{ __('dias com frequência sem conteúdo') }}</span></div>
                        <div><strong>{{ count($diaryPending['content_without_attendance']) }}</strong><span>{{ __('dias com conteúdo sem frequência') }}</span></div>
                        <div><strong>{{ $diaryPending['missing_grades'] }}</strong><span>{{ __('notas pendentes') }}</span></div>
                    </div>
                    <p class="small text-muted mb-3">{{ __('A confirmação fica disponível a partir do último dia do período, quando não houver pendências de conteúdo, frequência ou notas.') }}</p>
                    <form method="POST" action="{{ route('teacher-diaries.confirmation.confirm', [$schoolClass, $component]) }}">@csrf<input type="hidden" name="academic_period_id" value="{{ $period->id }}"><button class="btn btn-success" type="submit" @disabled(! $canConfirmPeriod || $diaryPending['attendance_without_content'] !== [] || $diaryPending['content_without_attendance'] !== [] || $diaryPending['missing_grades'] > 0)><i class="fas fa-check mr-1" aria-hidden="true"></i>{{ __('Confirmar lançamentos') }}</button></form>
                    @if(! $canConfirmPeriod)<p class="small text-muted mt-2 mb-0">{{ __('A confirmação será liberada em') }} {{ $period->ends_at->format('d/m/Y') }}.</p>@endif
                    @if ($confirmation?->reopened_at)<p class="small text-warning mt-3 mb-0"><i class="fas fa-history mr-1" aria-hidden="true"></i>{{ __('Reaberto por') }} {{ $confirmation->reopenedBy?->full_name ?? __('gestão') }}: {{ $confirmation->reopen_reason }}</p>@endif
                @endif
            </div>
        </section>
        </div>
        <div id="section-frequencia" class="card shadow mb-4" data-academic-panel="frequencia" role="tabpanel">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Frequência') }}</h2>
                <div class="btn-group mt-2 mt-md-0"><a class="btn btn-outline-primary btn-sm" href="{{ route('teacher-diaries.contents', [$schoolClass, $component, 'period' => $period->id]) }}"><i class="fas fa-book mr-1" aria-hidden="true"></i>{{ __('Conteúdos') }}</a><a class="btn btn-primary btn-sm" href="{{ route('teacher-diaries.attendance', [$schoolClass, $component, 'period' => $period->id]) }}"><i class="fas fa-clipboard-check mr-1" aria-hidden="true"></i>{{ __('Lançar frequência') }}</a></div>
            </div>
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                <p class="mb-0 mr-3">{{ __('Use a folha visual para lançar até 15 dias de uma vez, inclusive presenças parciais por aula.') }}</p>
                <span class="small text-muted">{{ $attendanceRecords->count() }} {{ __('chamada(s) ·') }} {{ $contents->count() }} {{ __('conteúdo(s) neste período') }}</span>
            </div>
        </div>

        <section id="section-notas" class="card shadow mb-4" aria-labelledby="grades-title" data-academic-panel="notas" role="tabpanel">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                <h2 id="grades-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Notas e média do período') }}</h2>
                @if ($assessmentRules->isNotEmpty())
                    <span class="small text-muted">{{ $assessmentRules->count() }} {{ __('avaliação(ões) definidas · Peso total') }} {{ $assessmentRules->sum('weight') }}</span>
                @endif
            </div>
            <div class="card-body">
                @if ($assessmentRules->isEmpty())
                    <p class="mb-0">{{ __('A gestão ainda não definiu as avaliações e os pesos deste período.') }}</p>
                @else
                    @php($activeEnrollmentCount = $enrollments->filter->isActive()->count())
                    <form method="POST" action="{{ route('teacher-diaries.grades.update', [$schoolClass, $component]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('Estudante') }}</th>
                                        @foreach ($assessments as $assessment)
                                            <th class="text-center">
                                                {{ $assessment->title }}
                                                <span class="d-block small text-muted">
                                                    @if ($assessment->is_recovery)
                                                        @if ($assessment->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_WEIGHTED)
                                                            {{ __('Peso') }} {{ $assessment->weight }}
                                                        @elseif ($assessment->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_LOWEST)
                                                            {{ __('Substitui a menor nota') }}
                                                        @elseif ($assessment->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE)
                                                            {{ __('Mantém a maior entre média e recuperação') }}
                                                        @else
                                                            {{ __('Substitui avaliação configurada') }}
                                                        @endif
                                                    @else
                                                        {{ __('Peso') }} {{ $assessment->weight }} {{ __('· Máx.') }} {{ $assessment->maximum_score }}
                                                    @endif
                                                </span>
                                            </th>
                                        @endforeach
                                        <th class="text-center">{{ __('Média (0–10)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($enrollments as $enrollment)
                                        @php($enrollmentLocked = ! $enrollment->isActive())
                                        @php($average = $averages[$enrollment->id] ?? ['value' => null, 'regular_value' => null, 'recovery_value' => null, 'recovery_required' => false, 'complete' => false, 'completed_assessments' => 0, 'total_assessments' => $assessments->where('is_recovery', false)->count()])
                                        <tr class="{{ $enrollmentLocked ? 'table-light' : '' }}">
                                            <td>
                                                {{ $enrollment->student?->full_name }}
                                                @if ($enrollmentLocked)
                                                    <span class="badge badge-secondary ml-1">{{ __($enrollment->statusLabel()) }}</span>
                                                @endif
                                            </td>
                                            @foreach ($assessments as $assessment)
                                                @php($result = $assessment->results->firstWhere('student_enrollment_id', $enrollment->id))
                                                <td><label class="sr-only" for="score_{{ $assessment->id }}_{{ $enrollment->id }}">{{ __('Nota de') }} {{ $enrollment->student?->full_name }} {{ __('em') }} {{ $assessment->title }}</label><input id="score_{{ $assessment->id }}_{{ $enrollment->id }}" name="scores[{{ $assessment->id }}][{{ $enrollment->id }}]" data-mask="decimal" inputmode="decimal" class="form-control form-control-sm" value="{{ $result?->score }}" @disabled($enrollmentLocked)>@if($assessment->is_recovery && $assessment->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE && ! $average['recovery_required'])<span class="d-block small text-muted mt-1">{{ __('Opcional; a média do período já atingiu a referência.') }}</span>@endif @if($result?->updatedBy && $result->updated_by_person_id !== $assignment->teacher_person_id)<span class="d-block small text-warning mt-1" title="{{ __('Lançamento alterado por') }} {{ $result->updatedBy->full_name }}"><i class="fas fa-user-shield" aria-hidden="true"></i><span class="sr-only">{{ __('Alterado por') }} {{ $result->updatedBy->full_name }}</span></span>@endif</td>
                                            @endforeach
                                            <td class="text-center font-weight-bold">{{ $average['value'] ?? '-' }}@if (($average['value'] ?? null) !== null)<span class="d-block small text-muted">{{ $average['complete'] ? __('Completa') : $average['completed_assessments'].__(' de ').$average['total_assessments'].__(' lançada(s)') }}</span>@if($average['recovery_value'] !== null)<span class="d-block small text-muted">{{ __('Original:') }} {{ $average['regular_value'] }} {{ __('· Recuperação:') }} {{ $average['recovery_value'] }}</span>@elseif($average['recovery_required'])<span class="d-block small text-muted">{{ __('Recuperação disponível') }}</span>@endif @endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-primary" type="submit" @disabled($activeEnrollmentCount === 0)>{{ __('Salvar notas') }}</button>
                    </form>
                @endif
            </div>
        </section>
    @endif
@endsection
