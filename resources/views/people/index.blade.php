@extends('layouts.app')

@section('title', 'Pessoas')
@section('page-title', 'Pessoas')

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('people.create') }}" aria-label="Cadastrar nova pessoa" title="Nova pessoa">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export sge-icon-action" href="{{ route('reports.excel', ['type' => 'people'] + request()->query()) }}" data-base-href="{{ route('reports.excel', 'people') }}" aria-label="Exportar pessoas filtradas para Excel" title="Exportar Excel">
        <i class="fas fa-file-excel" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export sge-icon-action" href="{{ route('reports.pdf', ['type' => 'people'] + request()->query()) }}" data-base-href="{{ route('reports.pdf', 'people') }}" aria-label="Exportar pessoas filtradas para PDF" title="Exportar PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:people-table />
        </div>
    </div>
@endsection
