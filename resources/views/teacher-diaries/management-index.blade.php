@extends('layouts.app')

@section('title', __('Acompanhamento dos diários'))
@section('page-title', __('Acompanhamento dos diários'))


@section('content')
    <livewire:screen-listing screen="diaries" :context="[]" />
@endsection
