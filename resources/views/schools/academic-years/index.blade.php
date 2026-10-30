@extends('layouts.app')

@section('title', __('screens.school_years'))
@section('page-title', __('screens.school_years_of', ['school' => $school->name]))

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('schools.academic-years.create', $school) }}" aria-label="{{ __('screens.register_school_year', ['school' => $school->name]) }}" title="{{ __('screens.new_school_year') }}">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="{{ __('screens.manage_criteria_of', ['name' => $school->name]) }}" title="{{ __('screens.criteria') }}">
        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
    </a>
    @if (auth()->user()?->canManageSchools())
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.edit', $school) }}" aria-label="{{ __('screens.edit_school', ['name' => $school->name]) }}" title="{{ __('screens.edit_school_title') }}">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
        <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('schools.index') }}" aria-label="{{ __('screens.back_schools') }}" title="{{ __('screens.back') }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <livewire:screen-listing screen="school-years" :context="['school' => $school->id]" />
@endsection
