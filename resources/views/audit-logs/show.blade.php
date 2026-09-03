@extends('layouts.app')

@section('title', __('screens.audit_detail'))
@section('page-title', __('screens.audit_detail'))

@section('content')
    @php
        $changes = \App\Support\AuditLogPresenter::changes($auditLog);
    @endphp

    <div class="card shadow mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('screens.when') }}</dt>
                <dd class="col-sm-9">
                    {{ $auditLog->created_at?->timezone($auditTimezone)->format('d/m/Y H:i:s') }}
                    <span class="text-muted small">({{ $auditTimezone }})</span>
                </dd>

                <dt class="col-sm-3">{{ __('screens.performed_by') }}</dt>
                <dd class="col-sm-9">{{ $auditLog->actorPerson?->full_name ?? $auditLog->actorUser?->name ?? __('screens.system') }}</dd>

                <dt class="col-sm-3">{{ __('screens.action') }}</dt>
                <dd class="col-sm-9">{{ \App\Support\AuditLogPresenter::actionLabel($auditLog->action) }}</dd>

                <dt class="col-sm-3">{{ __('screens.changed_record') }}</dt>
                <dd class="col-sm-9">{{ \App\Support\AuditLogPresenter::recordLabel($auditLog) }}</dd>

                <dt class="col-sm-3">{{ __('screens.school') }}</dt>
                <dd class="col-sm-9">{{ $auditLog->school?->name ?? __('screens.global') }}</dd>

                <dt class="col-sm-3">{{ __('screens.ip_address') }}</dt>
                <dd class="col-sm-9">{{ $auditLog->ip_address ?? '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header font-weight-bold">{{ __('screens.what_changed') }}</div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('screens.field') }}</th>
                        <th>{{ __('screens.before') }}</th>
                        <th>{{ __('screens.after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($changes as $change)
                        <tr>
                            <td>{{ $change['field'] }}</td>
                            <td>{{ \App\Support\AuditLogPresenter::value($change['old'], $change['key'], $auditLog->auditable_type) }}</td>
                            <td>{{ \App\Support\AuditLogPresenter::value($change['new'], $change['key'], $auditLog->auditable_type) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">{{ __('screens.no_field_change') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <details class="card shadow mb-4">
        <summary class="card-header font-weight-bold">{{ __('screens.technical_data') }}</summary>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h2 class="h6 font-weight-bold">{{ __('screens.previous_values') }}</h2>
                    <pre class="mb-0">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                <div class="col-lg-6">
                    <h2 class="h6 font-weight-bold">{{ __('screens.new_values') }}</h2>
                    <pre class="mb-0">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </details>
@endsection
