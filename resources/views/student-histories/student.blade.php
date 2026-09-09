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
    @php($history = $person->academicHistories->firstWhere('education_stage', $stage))
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
                <p class="text-muted mt-3 mb-0"><i class="fas fa-lock mr-1" aria-hidden="true"></i>{{ __('Esta etapa será liberada quando houver matrícula do estudante nela.') }}</p>
            @endif
        </div>
    </section></div>
@endforeach
</div>
@endsection
