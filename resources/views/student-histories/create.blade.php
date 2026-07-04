@extends('layouts.app')

@section('title', 'Novo histórico escolar')
@section('page-title', 'Novo histórico escolar')

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="Voltar ao cadastro" title="Voltar ao cadastro">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="alert alert-info">
        Cadastre o histórico exatamente como veio da escola de origem. Os nomes de componentes, cargas horárias, conceitos e resultados são livres.
    </div>

    <form method="POST" action="{{ route('people.histories.store', $person) }}">
        @csrf
        @include('student-histories._form')
    </form>
@endsection
