@extends('layouts.app')

@section('title', 'Pessoas')
@section('page-title', 'Pessoas')

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm" href="{{ route('people.create') }}">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nova pessoa
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('reports.excel', 'people') }}">
        <i class="fas fa-file-excel fa-sm"></i> Excel
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('reports.pdf', 'people') }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:people-table />
        </div>
    </div>
@endsection
