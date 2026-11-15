@extends('layouts.app')

@section('title', __('screens.announcements'))
@section('page-title', __('screens.announcements'))

@section('content')
    @php
        $announcementTabs = [
            'recados' => ['label' => __('screens.announcements'), 'icon' => 'fa-bullhorn'],
            'novo' => ['label' => __('screens.new_announcement'), 'icon' => 'fa-plus'],
        ];
    @endphp
    <div>
    <x-section-tabs :tabs="$announcementTabs" :label="__('Seções dos recados')" :default-tab="$errors->any() ? 'novo' : 'recados'" />
    <div id="section-novo" data-academic-panel="novo" role="tabpanel">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('screens.new_announcement') }}</h2>
        </div>
        <div class="card-body">
            @include('announcements.form')
        </div>
    </div>

    </div>
    <div id="section-recados" data-academic-panel="recados" role="tabpanel">
        <livewire:screen-listing screen="announcements" />
    </div>
    </div>
@endsection
