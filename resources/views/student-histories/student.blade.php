@extends('layouts.app')

@section('title', __('Históricos de ').$person->full_name)
@section('page-title', __('Históricos escolares'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.create', $person) }}" aria-label="{{ __('Adicionar anos externos') }}" title="{{ __('Adicionar anos externos') }}"><i class="fas fa-plus" aria-hidden="true"></i></a>
    <a data-navigation-return class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-histories.index') }}" aria-label="{{ __('Voltar aos históricos escolares') }}" title="{{ __('Voltar aos históricos') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
<section class="sge-student-profile sge-history-overview-person mb-3" aria-labelledby="history-student-name">
    <div class="sge-student-profile-main">
        <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($person->full_name, 0, 1) }}</div>
        <div>
            <div class="sge-page-kicker">{{ __('Estudante') }}</div>
            <h2 id="history-student-name">{{ $person->full_name }}</h2>
            @if($person->student_inep)<span class="text-muted">INEP {{ $person->student_inep }}</span>@endif
        </div>
    </div>
</section>

<section aria-labelledby="history-stages-heading">
    <div class="sge-history-overview-heading">
        <h2 id="history-stages-heading">{{ __('Etapas de ensino') }}</h2>
        <span class="text-muted" title="{{ __('Os dados das matrículas são atualizados ao abrir o histórico.') }}"><i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>{{ __('Atualização automática') }}</span>
    </div>
    <div class="sge-history-stage-list">
    @foreach([
        'fundamental' => [__('Ensino Fundamental'), 'fa-book-open'],
        'medio' => [__('Ensino Médio'), 'fa-graduation-cap'],
    ] as $stage => $definition)
        @php($history = $person->academicHistories->where('is_unified', true)->firstWhere('education_stage', $stage))
        @php($completeness = $history ? ($historyCompleteness[$history->id] ?? null) : null)
        @php($stageIsAvailable = in_array($stage, $availableHistoryStages, true))
        <article class="card sge-panel-card sge-history-stage {{ ! $history && ! $stageIsAvailable ? 'is-unavailable' : '' }}" data-history-stage="{{ $stage }}" aria-labelledby="history-stage-{{ $stage }}">
            <div class="sge-history-stage-icon" aria-hidden="true"><i class="fas {{ $definition[1] }}"></i></div>
            <div class="sge-history-stage-content">
                <div class="sge-history-stage-heading">
                    <h3 id="history-stage-{{ $stage }}">{{ $definition[0] }}</h3>
                    @if($completeness && ! $completeness['complete'])
                        <span class="badge badge-warning">{{ __('Conferir cadastro') }}</span>
                    @endif
                </div>
                @if($history)
                    <p class="sge-history-stage-counts">
                        <span><strong>{{ $history->years_count }}</strong> {{ $history->years_count === 1 ? __('ano cadastrado') : __('anos cadastrados') }}</span>
                        <span><strong>{{ $history->components_count }}</strong> {{ $history->components_count === 1 ? __('componente') : __('componentes') }}</span>
                    </p>
                    @if($completeness && ! $completeness['complete'])
                        <p class="sge-history-stage-notice"><i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i>{{ $completeness['message'] }}</p>
                    @endif
                @elseif($stageIsAvailable)
                    <p class="sge-history-stage-notice">{{ __('Disponível a partir das matrículas registradas.') }}</p>
                @else
                    <p class="sge-history-stage-notice">{{ __('Esta etapa será liberada quando houver matrícula do estudante nela, inclusive transferida ou encerrada.') }}</p>
                @endif
            </div>
            <div class="sge-history-stage-actions">
                @if($history)
                    <a class="btn btn-primary" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="{{ __('Abrir histórico') }} — {{ $definition[0] }}">{{ __('Abrir histórico') }}<i class="fas fa-arrow-right ml-2" aria-hidden="true"></i></a>
                    <a class="btn btn-outline-secondary sge-icon-action" href="{{ route('people.histories.edit', [$person, $history]) }}" aria-label="{{ __('Editar anos externos') }} — {{ $definition[0] }}" title="{{ __('Editar anos externos') }}"><i class="fas fa-pen" aria-hidden="true"></i></a>
                    @if($completeness && $completeness['complete'])
                        <a class="btn btn-outline-primary sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" target="_blank" rel="noopener" aria-label="{{ __('Emitir histórico em PDF') }} — {{ $definition[0] }}" title="{{ __('Emitir histórico em PDF') }}"><i class="fas fa-file-pdf" aria-hidden="true"></i></a>
                    @endif
                @elseif($stageIsAvailable)
                    <form method="post" action="{{ route('student-histories.unified', [$person, $stage]) }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit" aria-label="{{ __('Abrir histórico') }} — {{ $definition[0] }}">{{ __('Abrir histórico') }}<i class="fas fa-arrow-right ml-2" aria-hidden="true"></i></button>
                    </form>
                @else
                    <span class="text-muted small"><i class="fas fa-lock mr-1" aria-hidden="true"></i>{{ __('Sem matrícula nesta etapa') }}</span>
                @endif
            </div>
        </article>
    @endforeach
        @foreach($technicalCourses as $technicalCourse)
            @php($history = $person->academicHistories->where('is_unified', true)->firstWhere('technical_course_id', $technicalCourse->id))
            <article class="card sge-panel-card sge-history-stage" data-history-stage="tecnico">
                <div class="sge-history-stage-icon" aria-hidden="true"><i class="fas fa-tools"></i></div>
                <div class="sge-history-stage-content"><span class="text-muted small">{{ __('Ensino Técnico Profissionalizante') }}</span><div class="sge-history-stage-heading"><h3>{{ $technicalCourse->name }}</h3></div><p class="text-muted mb-0">{{ $technicalCourse->academicYear->school->name }} · {{ $technicalCourse->academicYear->name }}</p></div>
                <div class="sge-history-stage-actions">
                    @if($history)
                        <a class="btn btn-primary" href="{{ route('people.histories.show', [$person, $history]) }}">{{ __('Abrir histórico') }}</a>
                        <a class="btn btn-outline-secondary sge-icon-action" href="{{ route('people.histories.edit', [$person, $history]) }}" aria-label="{{ __('Editar anos externos') }} — {{ $technicalCourse->name }}"><i class="fas fa-pen" aria-hidden="true"></i></a>
                    @else
                        <form method="post" action="{{ route('student-histories.unified', [$person, 'tecnico']) }}">@csrf
                            <input type="hidden" name="technical_course_id" value="{{ $technicalCourse->id }}">
                            <button class="btn btn-outline-primary" type="submit">{{ __('Abrir histórico') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@if($unidentifiedTechnicalHistories->isNotEmpty())
<section class="card shadow mt-3"><div class="card-body">
<h2 class="h5">{{ __('Identificar cursos dos lançamentos técnicos antigos') }}</h2>
<p class="text-muted">{{ __('Os anos manuais foram preservados. Selecione a qual curso cada cadastro pertence antes de incorporá-lo ao histórico.') }}</p>
@foreach($unidentifiedTechnicalHistories as $unidentified)
<form method="post" action="{{ route('people.histories.identify-course', [$person, $unidentified]) }}" class="mb-3">@csrf
<label for="history-course-{{ $unidentified->id }}">{{ $unidentified->title }} #{{ $unidentified->id }}</label>
<div class="d-flex flex-wrap" style="gap: .75rem"><select class="form-control flex-grow-1" style="min-width: 0; max-width: 100%" id="history-course-{{ $unidentified->id }}" name="technical_course_id" required>
<option value="">{{ __('Selecione o curso') }}</option>
@foreach($technicalCourses as $course)<option value="{{ $course->id }}">{{ $course->name }} · {{ $course->academicYear->name }}</option>@endforeach
</select><button class="btn btn-primary" type="submit">{{ __('Identificar e incorporar') }}</button></div>
</form>
@endforeach
</div></section>
@endif
@if($separateHistories->isNotEmpty())
<section class="card shadow sge-panel-card mt-4" aria-labelledby="separate-histories-title">
    <div class="sge-panel-header"><div>
        <h2 id="separate-histories-title">{{ __('Lançamentos que precisam de identificação da etapa') }}</h2>
        <p>{{ __('Identifique a etapa de cada ano para incorporar os dados ao documento único correspondente. A emissão fica disponível apenas no histórico da etapa.') }}</p>
    </div></div>
    <div class="card-body">
        @foreach($separateHistories as $separateHistory)
            <div class="mb-4">
                <h3 class="h5">{{ $separateHistory->title }} #{{ $separateHistory->id }}</h3>
                <a class="btn btn-sm btn-outline-primary mb-2" href="{{ route('people.histories.edit', [$person, $separateHistory]) }}">{{ __('Editar anos e cargas horárias') }}</a>
                <a class="btn btn-sm btn-outline-secondary mb-2" href="{{ route('people.histories.show', [$person, $separateHistory]) }}">{{ __('Visualizar lançamentos') }}</a>
                <div class="table-responsive"><table class="table table-sm table-bordered" data-sge-client-pagination="10">
                    <thead><tr><th>{{ __('Ano civil') }}</th><th>{{ __('Etapa') }}</th><th>{{ __('Série/Fase') }}</th><th>{{ __('CH total') }}</th><th>{{ __('Ações') }}</th></tr></thead>
                    <tbody>
                    @foreach($separateHistory->years as $year)
                        @php($yearStage = array_search($year->stage, \App\Models\AcademicCourse::STAGE_LABELS, true))
                        @php($gradeNumber = $year->grade_number ?: app(\App\Support\StudentAcademicHistoryCompleteness::class)->gradeNumber((string) $year->grade_phase, $yearStage ?: null))
                        @php($alreadyImported = $person->academicHistories->where('is_unified', true)->contains(fn ($unified) => $unified->years->contains('imported_from_history_year_id', $year->id)))
                        <tr>
                            <td>{{ $year->year ?: '-' }}</td><td>{{ $year->stage ?: '-' }}</td>
                            <td>{{ $year->grade_phase ?: '-' }}@if($gradeNumber)<small class="d-block text-muted">{{ __('Corresponde ao') }} {{ $gradeNumber }}º {{ __('ano') }}</small>@endif</td>
                            <td>{{ $year->workload_hours !== null ? number_format((float) $year->workload_hours, 2, ',', '.').' h' : '-' }}</td>
                            <td>
                                @if($alreadyImported)<span class="badge badge-success">{{ __('Já aproveitado') }}</span>
                                @elseif($yearStage && $gradeNumber && in_array($yearStage, $availableHistoryStages, true))
                                    <form method="post" action="{{ route('student-histories.import-year', [$person, $yearStage, $year]) }}">@csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Aproveitar na etapa') }}</button>
                                    </form>
                                @else
                                    <a href="{{ route('people.histories.edit', [$person, $separateHistory]) }}">{{ __('Identificar etapa e série') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endforeach
    </div>
</section>
@endif
@endsection
