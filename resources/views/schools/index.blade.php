@extends('layouts.app')

@section('title', 'Escolas')
@section('page-title', 'Escolas')

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm" href="{{ route('schools.create') }}">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nova escola
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('reports.excel', 'schools') }}">
        <i class="fas fa-file-excel fa-sm"></i> Excel
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('reports.pdf', 'schools') }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:schools-table />
        </div>
    </div>
@endsection
