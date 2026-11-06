@extends('layouts.app')

@section('title', __('Adicionar anos externos ao histórico'))
@section('page-title', __('Adicionar anos externos ao histórico'))

@section('page-actions')
    <a data-navigation-return class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="{{ __('Voltar ao cadastro') }}" title="{{ __('Voltar ao cadastro') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="alert alert-info">
        {{ __('Transcreva os anos conforme o documento da escola de origem. Os dados serão acrescentados ao histórico único de cada etapa, junto às matrículas registradas no sistema.') }}
    </div>

    <form method="POST" action="{{ route('people.histories.store', $person) }}">
        @csrf
        @include('student-histories._form')
    </form>
@endsection
