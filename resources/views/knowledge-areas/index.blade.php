@extends('layouts.app')
@section('title', __('Áreas e formações'))
@section('page-title', __('Áreas e formações'))
@section('content')
    <livewire:screen-listing screen="knowledge-areas" :context="[]" />
@endsection
