@extends('layouts.app')

@section('title', __('Justificativas de ausência'))
@section('page-title', __('Justificativas de ausência'))

@section('content')
    <livewire:screen-listing screen="justifications" :context="[]" />
@endsection
