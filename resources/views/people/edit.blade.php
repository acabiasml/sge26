@extends('layouts.app')

@section('title', 'Editar Pessoa')
@section('page-title', 'Editar Pessoa')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('people.update', $person) }}">
                @csrf
                @method('PUT')

                @include('people._form', [
                    'person' => $person,
                    'lockInstitutionalEmail' => $lockInstitutionalEmail ?? false,
                ])

                <button class="btn btn-primary" type="submit">Salvar alterações</button>
                <a class="btn btn-secondary" href="{{ route('people.show', $person) }}">Voltar</a>
            </form>
        </div>
    </div>
@endsection
