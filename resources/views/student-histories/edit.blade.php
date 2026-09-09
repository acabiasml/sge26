@extends('layouts.app')

@section('title', $history->is_unified ? __('Séries cursadas em outras escolas') : __('Matriz curricular do histórico'))
@section('page-title', $history->is_unified ? __('Séries cursadas em outras escolas') : __('Matriz curricular do histórico'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" aria-label="{{ __('Emitir histórico em PDF') }}" title="{{ __('Histórico em PDF') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="{{ __('Voltar ao histórico') }}" title="{{ __('Voltar ao histórico') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @if($history->is_unified)
        <div class="alert alert-info">
            <strong>{{ __('Os dados das nossas escolas são automáticos e não podem ser alterados aqui.') }}</strong>
            {{ __('Esta tela cadastra somente séries cursadas em outras instituições. Matrículas, notas, frequência, faltas, resultados e cargas horárias internas vêm diretamente do sistema.') }}
        </div>
    @endif
    <form method="POST" action="{{ route('people.histories.update', [$person, $history]) }}">
        @csrf
        @method('PUT')
        @include('student-histories._form', ['curriculumOnly' => true, 'manualOnly' => $history->is_unified])
    </form>
@endsection
