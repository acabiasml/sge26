@extends('layouts.app')

@section('title', __('screens.schools'))
@section('page-title', __('screens.schools'))

@section('page-actions')
    @if (auth()->user()?->canManageSchools())
        <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('schools.create') }}" aria-label="{{ __('screens.register_school') }}" title="{{ __('screens.new_school') }}">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
    @endif
    <a class="btn btn-sm btn-outline-primary shadow-sm js-current-query-export sge-icon-action" href="{{ route('reports.excel', ['type' => 'schools'] + request()->query()) }}" data-base-href="{{ route('reports.excel', 'schools') }}" aria-label="{{ __('screens.export_filtered_schools_excel') }}" title="{{ __('screens.export_excel') }}">
        <i class="fas fa-file-excel" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm js-current-query-export sge-icon-action" href="{{ route('reports.pdf', ['type' => 'schools'] + request()->query()) }}" data-base-href="{{ route('reports.pdf', 'schools') }}" aria-label="{{ __('screens.export_filtered_schools_pdf') }}" title="{{ __('screens.export_pdf') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4 sge-livewire-table-card">
        <div class="card-body">
            <livewire:schools-table />
        </div>
    </div>
@endsection
