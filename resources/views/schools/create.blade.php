@extends('layouts.app')

@section('title', __('screens.new_school_title'))
@section('page-title', __('screens.new_school_title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.store') }}" enctype="multipart/form-data">
                @csrf
                @include('schools._form', ['school' => new \App\Models\School(['active' => true])])
                <button class="btn btn-primary" type="submit">{{ __('screens.save_school') }}</button>
                <a class="btn btn-secondary" href="{{ route('schools.index') }}">{{ __('screens.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
