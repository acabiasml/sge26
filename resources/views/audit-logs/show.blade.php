@extends('layouts.app')

@section('title', 'Detalhe da Auditoria')
@section('page-title', 'Detalhe da Auditoria')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Quando</dt>
                <dd class="col-sm-9">
                    {{ $auditLog->created_at?->timezone($auditTimezone)->format('d/m/Y H:i:s') }}
                    <span class="text-muted small">({{ $auditTimezone }})</span>
                </dd>
                <dt class="col-sm-3">Quem</dt>
                <dd class="col-sm-9">{{ $auditLog->actorPerson?->full_name ?? $auditLog->actorUser?->name ?? 'Sistema' }}</dd>
                <dt class="col-sm-3">Ação</dt>
                <dd class="col-sm-9">{{ $auditLog->action }}</dd>
                <dt class="col-sm-3">Registro</dt>
                <dd class="col-sm-9">{{ $auditLog->auditable_type }} #{{ $auditLog->auditable_id }}</dd>
                <dt class="col-sm-3">Escola</dt>
                <dd class="col-sm-9">{{ $auditLog->school?->name ?? '-' }}</dd>
                <dt class="col-sm-3">IP</dt>
                <dd class="col-sm-9">{{ $auditLog->ip_address ?? '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Valores anteriores</div>
                <div class="card-body">
                    <pre class="mb-0">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header font-weight-bold">Valores novos</div>
                <div class="card-body">
                    <pre class="mb-0">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
