@extends('layouts.app')

@section('title', __('Históricos de ').$person->full_name)
@section('page-title', __('Históricos escolares'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-histories.index') }}" aria-label="{{ __('Voltar aos históricos escolares') }}" title="{{ __('Voltar aos históricos') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
<section class="sge-student-profile mb-4">
    <div class="sge-student-profile-main"><div class="sge-avatar-lg">{{ mb_substr($person->full_name, 0, 1) }}</div><div><div class="sge-page-kicker">{{ __('Estudante matriculado') }}</div><h2>{{ $person->full_name }}</h2><span class="text-muted">INEP {{ $person->student_inep ?: __('não informado') }}</span></div></div>
</section>

<div class="row">
@foreach([
    'fundamental' => [__('Ensino Fundamental'), __('9 anos'), __('Formação Geral Básica e Parte Diversificada')],
    'medio' => [__('Ensino Médio'), __('3 anos'), __('Formação Geral Básica e Itinerário Formativo')],
    'tecnico' => [__('Ensino Técnico Profissionalizante'), __('Curso subsequente'), __('Formação técnica independente do Ensino Médio')],
] as $stage => $definition)
    @php($history = $person->academicHistories->where('is_unified', true)->firstWhere('education_stage', $stage))
    @php($completeness = $history ? ($historyCompleteness[$history->id] ?? null) : null)
    @php($stageIsAvailable = in_array($stage, $availableHistoryStages, true))
    <div class="col-lg-4"><section class="card shadow sge-panel-card mb-4 h-100">
        <div class="sge-panel-header"><div><h2>{{ $definition[0] }}</h2><p>{{ $definition[1] }} · {{ $definition[2] }}</p></div></div>
        <div class="card-body">
            @if($history)
                <p><strong>{{ $history->years_count }}</strong> {{ __('ano(s) e') }} <strong>{{ $history->components_count }}</strong> {{ __('componente(s) cadastrados.') }}</p>
                @if($completeness && ! $completeness['complete'])
                    <div class="alert alert-warning py-2"><i class="fas fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ $completeness['message'] }}</div>
                @endif
                <a class="btn btn-outline-primary" href="{{ route('people.histories.show', [$person, $history]) }}">{{ __('Abrir histórico') }}</a>
            @else
                <p class="text-muted">{{ __('O arquivo desta etapa ainda não foi iniciado.') }}</p>
            @endif
            @if($history || $stageIsAvailable)
                <form method="post" action="{{ route('student-histories.unified', [$person, $stage]) }}" class="mt-3">@csrf<button class="btn btn-primary" type="submit">{{ $history ? __('Atualizar dados das matrículas') : __('Iniciar arquivo desta etapa') }}</button></form>
            @else
                <p class="text-muted mt-3 mb-0"><i class="fas fa-lock mr-1" aria-hidden="true"></i>{{ __('Esta etapa será liberada quando houver matrícula do estudante nela, inclusive transferida ou encerrada.') }}</p>
            @endif
        </div>
    </section></div>
@endforeach
</div>
@if($separateHistories->isNotEmpty())
<section class="card shadow sge-panel-card mt-4" aria-labelledby="separate-histories-title">
    <div class="sge-panel-header"><div>
        <h2 id="separate-histories-title">{{ __('Históricos cadastrados separadamente') }}</h2>
        <p>{{ __('Os anos lançados antes do histórico unificado estão aqui. Revise a etapa, a série e a carga horária; depois aproveite cada ano na etapa correspondente.') }}</p>
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
