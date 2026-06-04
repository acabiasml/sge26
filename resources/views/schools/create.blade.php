@extends('layouts.app')

@section('title', 'Nova Escola')
@section('page-title', 'Nova Escola')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.store') }}">
                @csrf
                @include('schools._form', ['school' => new \App\Models\School(['active' => true])])
                <button class="btn btn-primary" type="submit">Salvar escola</button>
                <a class="btn btn-secondary" href="{{ route('schools.index') }}">Cancelar</a>
            </form>
        </div>
    </div>
@endsection
