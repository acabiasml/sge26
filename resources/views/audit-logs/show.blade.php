@extends('layouts.app')

@section('title', 'Detalhe da auditoria')
@section('page-title', 'Detalhe da auditoria')

@section('content')
    @php
        $changes = \App\Support\AuditLogPresenter::changes($auditLog);
    @endphp

    <div class="card shadow mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Quando</dt>
                <dd class="col-sm-9">
                    {{ $auditLog->created_at?->timezone($auditTimezone)->format('d/m/Y H:i:s') }}
                    <span class="text-muted small">({{ $auditTimezone }})</span>
                </dd>

                <dt class="col-sm-3">Quem fez</dt>
                <dd class="col-sm-9">{{ $auditLog->actorPerson?->full_name ?? $auditLog->actorUser?->name ?? 'Sistema' }}</dd>

                <dt class="col-sm-3">Ação</dt>
                <dd class="col-sm-9">{{ \App\Support\AuditLogPresenter::actionLabel($auditLog->action) }}</dd>

                <dt class="col-sm-3">Registro alterado</dt>
                <dd class="col-sm-9">{{ \App\Support\AuditLogPresenter::recordLabel($auditLog) }}</dd>

                <dt class="col-sm-3">Escola</dt>
                <dd class="col-sm-9">{{ $auditLog->school?->name ?? 'Global' }}</dd>

                <dt class="col-sm-3">Endereço IP</dt>
                <dd class="col-sm-9">{{ $auditLog->ip_address ?? '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header font-weight-bold">O que mudou</div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Campo</th>
                        <th>Antes</th>
                        <th>Depois</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($changes as $change)
                        <tr>
                            <td>{{ $change['field'] }}</td>
                            <td>{{ \App\Support\AuditLogPresenter::value($change['old']) }}</td>
                            <td>{{ \App\Support\AuditLogPresenter::value($change['new']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhuma alteração de campo foi registrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <details class="card shadow mb-4">
        <summary class="card-header font-weight-bold">Dados técnicos</summary>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h2 class="h6 font-weight-bold">Valores anteriores</h2>
                    <pre class="mb-0">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                <div class="col-lg-6">
                    <h2 class="h6 font-weight-bold">Valores novos</h2>
                    <pre class="mb-0">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </details>
@endsection
