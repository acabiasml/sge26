@extends('layouts.app')

@section('title', __('Meu diário'))
@section('page-title', __('Meu diário'))

@section('content')
    <livewire:screen-listing screen="student-diaries" :context="[]" />
@endsection
