@extends('layouts.app')

@section('title', 'Editar Escola')
@section('page-title', 'Editar Escola')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('schools.pdf', $school) }}">
        <i class="fas fa-file-pdf fa-sm"></i> Ficha em PDF
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.update', $school) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('schools._form', ['school' => $school])
                <button class="btn btn-primary" type="submit">Salvar alterações</button>
                <a class="btn btn-secondary" href="{{ route('schools.index') }}">Voltar</a>
            </form>
        </div>
    </div>
@endsection
