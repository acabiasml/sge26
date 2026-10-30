@extends('layouts.app')

@section('title', __('screens.announcements'))
@section('page-title', __('screens.announcements'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('screens.new_announcement') }}</h2>
        </div>
        <div class="card-body">
            @include('announcements.form')
        </div>
    </div>

    <livewire:screen-listing screen="announcements" />
@endsection
