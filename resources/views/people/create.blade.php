@extends('layouts.app')

@section('title', __('screens.new_person_title'))
@section('page-title', __('screens.new_person_title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('people.store') }}">
                @csrf
                @include('people._form', ['person' => new \App\Models\Person()])

                <hr>
                <h2 class="h5 text-gray-900">{{ __('screens.initial_relationship') }}</h2>

                @if (! $requiresInitialRole)
                    <p class="text-gray-600">{{ __('screens.initial_relationship_optional') }}</p>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="initial_role">{{ __('screens.role') }}</label>
                        <select id="initial_role" name="initial_role" class="form-control @error('initial_role') is-invalid @enderror" data-role-select @required($requiresInitialRole)>
                            <option value="">{{ __('screens.select') }}</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('initial_role') === $value)>{{ __('roles.roles.'.$value) }}</option>
                            @endforeach
                        </select>
                        @error('initial_role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-4">
                        <label for="initial_school_id">{{ __('screens.school') }}</label>
                        <select id="initial_school_id" name="initial_school_id" class="form-control @error('initial_school_id') is-invalid @enderror" @required($requiresInitialRole)>
                            <option value="">{{ __('screens.global_without_school') }}</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected((int) old('initial_school_id') === $school->id)>{{ $school->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">{{ __('screens.administration_global_help') }}</small>
                        @error('initial_school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-4">
                        <label for="initial_position">{{ __('screens.management_area') }}</label>
                        <select id="initial_position" name="initial_position" class="form-control @error('initial_position') is-invalid @enderror" data-manager-position>
                            <option value="">{{ __('screens.select_manager_role') }}</option>
                            @foreach ($positions as $value => $label)
                                <option value="{{ $value }}" @selected(old('initial_position') === $value)>{{ __('roles.positions.'.$value) }}</option>
                            @endforeach
                        </select>
                        @error('initial_position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="initial_started_at">{{ __('screens.start') }}</label>
                        <input id="initial_started_at" name="initial_started_at" type="date" class="form-control @error('initial_started_at') is-invalid @enderror" value="{{ old('initial_started_at') }}">
                        <small class="form-text text-muted">{{ __('screens.school_relationship_start_help') }}</small>
                        @error('initial_started_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-6">
                        <label for="initial_ended_at">{{ __('screens.end') }}</label>
                        <input id="initial_ended_at" name="initial_ended_at" type="date" class="form-control @error('initial_ended_at') is-invalid @enderror" value="{{ old('initial_ended_at') }}">
                        @error('initial_ended_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('screens.save_person') }}</button>
                <a class="btn btn-secondary" href="{{ route('people.index') }}">{{ __('screens.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
