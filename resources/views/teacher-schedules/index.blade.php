@extends('layouts.app')

@section('title', __('Meu horário'))
@section('page-title', __('Meu horário'))

@section('page-actions')
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.pdf') }}" aria-label="{{ __('Imprimir meu horário docente') }}" title="{{ __('Imprimir meu horário') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.index') }}" aria-label="{{ __('Voltar aos diários') }}" title="{{ __('Voltar aos diários') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <livewire:screen-listing screen="teacher-schedules" :context="[]" />
@endsection
