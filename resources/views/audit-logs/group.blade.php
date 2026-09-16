@extends('layouts.app')
@section('title', __('Registros agrupados'))
@section('page-title', __('Registros agrupados'))
@section('page-actions')
    <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" aria-label="{{ __('Voltar para Auditoria') }}" title="{{ __('Voltar para Auditoria') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
<div class="card shadow mb-4"><div class="card-body">
    <h2 class="h5">{{ \App\Support\AuditLogGroups::label($group) }}</h2>
    <p class="mb-1">{{ $group->actorPerson?->full_name ?? $group->actorUser?->name ?? __('screens.system') }} · {{ \App\Support\AuditLogPresenter::actionLabel($group->action) }} · {{ $group->school?->name ?? __('screens.global') }}</p>
    <p class="text-muted mb-0">{{ __(':count registros em sequência', ['count' => $group->group_count]) }}. {{ __('Os registros originais e os detalhes de cada alteração foram preservados.') }}</p>
</div></div>
<div class="card shadow mb-4"><div class="card-body table-responsive">
    <table class="table"><thead><tr><th scope="col">{{ __('screens.when') }}</th><th scope="col">{{ __('screens.record') }}</th><th scope="col">{{ __('screens.details') }}</th></tr></thead><tbody>
    @foreach ($records as $record)
        <tr><td>{{ $record->created_at->timezone($auditTimezone)->format('d/m/Y H:i:s') }}</td><td>{{ \App\Support\AuditLogPresenter::recordLabel($record) }}</td><td>@include('livewire.tables.audit-log-actions', ['auditLog' => $record])</td></tr>
    @endforeach
    </tbody></table>
    {{ $records->links() }}
</div></div>
@endsection
