@extends('layouts.app')

@section('title', __('navigation.compliance'))
@section('page-title', __('screens.document_academic_compliance'))

@section('content')
    <livewire:screen-listing screen="quality" :context="[]" />
@endsection
