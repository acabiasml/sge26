@extends('layouts.app')

@section('title', __('screens.enrollments'))
@section('page-title', __('screens.enrollments'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('data-quality.index') }}" aria-label="{{ __('Abrir conformidade antes de matricular') }}" title="{{ __('Conformidade') }}">
        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <livewire:screen-listing screen="enrollment-overview" :context="[]" />
@endsection
