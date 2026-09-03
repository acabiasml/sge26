@extends('layouts.app')

@section('title', __('screens.edit_school_page'))
@section('page-title', __('screens.edit_school_page'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.academic-years.index', $school) }}" aria-label="{{ __('screens.manage_school_years', ['name' => $school->name]) }}" title="{{ __('screens.school_years') }}">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.pdf', $school) }}" aria-label="{{ __('screens.issue_pdf_of', ['name' => $school->name]) }}" title="{{ __('screens.pdf_record') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="{{ __('screens.manage_criteria_of', ['name' => $school->name]) }}" title="{{ __('screens.criteria') }}">
        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.update', $school) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('schools._form', ['school' => $school])
                <button class="btn btn-primary" type="submit">{{ __('screens.save_changes') }}</button>
                <a class="btn btn-secondary" href="{{ route('schools.index') }}">{{ __('screens.back') }}</a>
            </form>
        </div>
    </div>
@endsection
