@extends('layouts.app')
@section('title', __('Áreas e formações'))
@section('page-title', __('Áreas e formações'))
@section('page-actions')
    <a class="btn btn-outline-primary sge-icon-action" href="{{ route('history-curriculum.index') }}" aria-label="{{ __('Catálogo de componentes') }}" title="{{ __('Catálogo de componentes') }}"><i class="fas fa-book" aria-hidden="true"></i><span class="sr-only">{{ __('Catálogo de componentes') }}</span></a>
@endsection
@section('content')
    <livewire:screen-listing screen="knowledge-areas" :context="[]" />
@endsection
