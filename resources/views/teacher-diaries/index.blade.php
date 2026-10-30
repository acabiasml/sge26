@extends('layouts.app')

@section('title', __('Diários'))
@section('page-title', $isManagement ? __('Diários das escolas') : __('Meus diários'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.index') }}" aria-label="{{ __('Abrir meu horário docente') }}" title="{{ __('Meu horário') }}">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.pdf') }}" aria-label="{{ __('Imprimir meu horário docente') }}" title="{{ __('Imprimir meu horário') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <livewire:screen-listing screen="diaries" :context="[]" />
@endsection
