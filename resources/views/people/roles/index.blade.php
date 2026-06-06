@extends('layouts.app')

@section('title', 'Vínculos')
@section('page-title', 'Vínculos')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm js-current-query-export" href="{{ route('reports.excel', ['type' => 'roles'] + request()->query()) }}" data-base-href="{{ route('reports.excel', 'roles') }}">
        <i class="fas fa-file-excel fa-sm"></i> Excel
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export" href="{{ route('reports.pdf', ['type' => 'roles'] + request()->query()) }}" data-base-href="{{ route('reports.pdf', 'roles') }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:roles-table />
        </div>
    </div>
@endsection
