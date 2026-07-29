@extends('layouts.app')

@section('title', 'Escolas')
@section('page-title', 'Escolas')

@section('page-actions')
    @if (auth()->user()?->canManageSchools())
        <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('schools.create') }}" aria-label="Cadastrar nova escola" title="Nova escola">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
    @endif
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export sge-icon-action" href="{{ route('reports.excel', ['type' => 'schools'] + request()->query()) }}" data-base-href="{{ route('reports.excel', 'schools') }}" aria-label="Exportar escolas filtradas para Excel" title="Exportar Excel">
        <i class="fas fa-file-excel" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export sge-icon-action" href="{{ route('reports.pdf', ['type' => 'schools'] + request()->query()) }}" data-base-href="{{ route('reports.pdf', 'schools') }}" aria-label="Exportar escolas filtradas para PDF" title="Exportar PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:schools-table />
        </div>
    </div>
@endsection
