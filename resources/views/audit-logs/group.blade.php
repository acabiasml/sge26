@extends('layouts.app')
@section('title', __('Registros agrupados'))
@section('page-title', __('Registros agrupados'))
@section('page-actions')
    <a data-navigation-return href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" aria-label="{{ __('Voltar para Auditoria') }}" title="{{ __('Voltar para Auditoria') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
<div class="card shadow mb-4"><div class="card-body">
    <h2 class="h5">{{ \App\Support\AuditLogGroups::label($group) }}</h2>
    <p class="mb-1">{{ $group->actorPerson?->full_name ?? $group->actorUser?->name ?? __('screens.system') }} · {{ \App\Support\AuditLogPresenter::actionLabel($group->action) }} · {{ $group->school?->name ?? __('screens.global') }}</p>
    <p class="text-muted mb-0">{{ __(':count registros em sequência', ['count' => $group->group_count]) }}. {{ __('Os registros originais e os detalhes de cada alteração foram preservados.') }}</p>
</div></div>
<livewire:screen-listing screen="audit-group" :context="['auditLog' => request()->route('auditLog')->id]" />
@endsection
