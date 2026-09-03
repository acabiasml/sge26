@extends('layouts.app')

@section('title', __('screens.edit_person'))
@section('page-title', __('screens.edit_person'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('people.update', $person) }}">
                @csrf
                @method('PUT')

                @include('people._form', [
                    'person' => $person,
                    'lockInstitutionalEmail' => $lockInstitutionalEmail ?? false,
                ])

                <button class="btn btn-primary" type="submit">{{ __('screens.save_changes') }}</button>
                <a class="btn btn-secondary" href="{{ route('people.show', $person) }}">{{ __('screens.back') }}</a>
            </form>
        </div>
    </div>
@endsection
