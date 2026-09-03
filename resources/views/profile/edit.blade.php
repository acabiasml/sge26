@extends('layouts.app')

@section('title', __('screens.my_profile'))
@section('page-title', __('screens.my_profile'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="text-gray-700">{{ __('screens.confirm_personal_data') }}</p>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                @include('people._form', [
                    'person' => $person,
                    'lockInstitutionalEmail' => $lockInstitutionalEmail ?? true,
                    'lockOwnIdentity' => $lockOwnIdentity ?? true,
                    'showActiveControl' => false,
                ])

                <button class="btn btn-primary" type="submit">{{ __('screens.save_continue') }}</button>
            </form>
        </div>
    </div>
@endsection
