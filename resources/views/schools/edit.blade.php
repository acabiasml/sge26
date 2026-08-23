@extends('layouts.app')

@section('title', 'Editar Escola')
@section('page-title', 'Editar Escola')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.academic-years.index', $school) }}" aria-label="Gerenciar anos letivos de {{ $school->name }}" title="Anos letivos">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.pdf', $school) }}" aria-label="Emitir ficha em PDF de {{ $school->name }}" title="Ficha em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="Gerenciar conceitos e critérios de {{ $school->name }}" title="Conceitos e critérios">
        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
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
