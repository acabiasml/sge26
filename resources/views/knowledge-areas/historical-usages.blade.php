@extends('layouts.app')
@section('title', __('Áreas registradas nos históricos'))
@section('page-title', __('Áreas registradas nos históricos'))
@section('content')
    <livewire:screen-listing screen="historical-usages" :context="[]" />
@endsection
