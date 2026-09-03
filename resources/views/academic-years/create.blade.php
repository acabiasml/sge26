@extends('layouts.app')

@section('title', __('screens.new_school_year'))
@section('page-title', __('screens.new_school_year_of', ['school' => $school->name]))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.academic-years.store', $school) }}">
                @include('academic-years._form', ['school' => $school])
            </form>
        </div>
    </div>
@endsection
