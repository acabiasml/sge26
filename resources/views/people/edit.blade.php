@extends('layouts.app')

@section('title', __('screens.edit_person'))
@section('page-title', __('screens.edit_person'))

@section('page-actions')
    @if(auth()->user()->canManagePeople())
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.index') }}" data-people-return aria-label="{{ __('Voltar para Pessoas') }}" title="{{ __('Voltar para Pessoas') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
    @endif
@endsection

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

@push('scripts')
    <script src="{{ asset('template/js/sge-people-navigation.js') }}" data-people-list-url="{{ route('people.index') }}" defer></script>
@endpush
