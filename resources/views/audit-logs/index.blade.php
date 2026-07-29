@extends('layouts.app')

@section('title', 'Auditoria')
@section('page-title', 'Auditoria')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm js-current-query-export sge-icon-action" href="{{ route('reports.excel', ['type' => 'audit-logs'] + request()->query()) }}" data-base-href="{{ route('reports.excel', 'audit-logs') }}" aria-label="Exportar auditoria filtrada para Excel" title="Exportar Excel">
        <i class="fas fa-file-excel" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 js-current-query-export sge-icon-action" href="{{ route('reports.pdf', ['type' => 'audit-logs'] + request()->query()) }}" data-base-href="{{ route('reports.pdf', 'audit-logs') }}" aria-label="Exportar auditoria filtrada para PDF" title="Exportar PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @if (auth()->user()->isAdministrator())
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('audit-logs.timezone.update') }}" class="form-inline">
                    @csrf
                    @method('PATCH')

                    <label for="audit_timezone" class="mr-2 mb-2">Fuso horário de visualização</label>
                    <select id="audit_timezone" name="audit_timezone" class="form-control mr-2 mb-2 @error('audit_timezone') is-invalid @enderror">
                        @foreach ($auditTimezones as $value => $label)
                            <option value="{{ $value }}" @selected($auditTimezone === $value)>{{ $label }} ({{ $value }})</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary mb-2" type="submit">Aplicar</button>

                    @error('audit_timezone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow mb-4 sge-livewire-table-card">
        <div class="card-body">
            <livewire:audit-logs-table :audit-timezone="$auditTimezone" />
        </div>
    </div>
@endsection
