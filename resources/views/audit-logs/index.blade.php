@extends('layouts.app')

@section('title', 'Auditoria')
@section('page-title', 'Auditoria')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('reports.excel', 'audit-logs') }}">
        <i class="fas fa-file-excel fa-sm"></i> Excel
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2" href="{{ route('reports.pdf', 'audit-logs') }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
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

    <div class="card shadow mb-4">
        <div class="card-body">
            <livewire:audit-logs-table :audit-timezone="$auditTimezone" />
        </div>
    </div>
@endsection
