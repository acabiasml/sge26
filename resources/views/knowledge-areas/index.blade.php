@extends('layouts.app')
@section('title', __('Áreas e formações'))
@section('page-title', __('Áreas e formações'))

@section('content')
    @include('knowledge-areas._navigation')
    <livewire:screen-listing screen="knowledge-areas" :context="[]" />
@endsection
