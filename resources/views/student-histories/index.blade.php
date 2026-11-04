@extends('layouts.app')

@section('title', __('screens.school_histories'))
@section('page-title', __('screens.school_histories'))

@section('content')
    <livewire:screen-listing screen="histories" :context="[]" />
@endsection
