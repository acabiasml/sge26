@extends('layouts.app')

@section('title', __('screens.my_profile'))
@section('page-title', __('screens.my_profile'))

@section('content')
    @php
        $profileTabs = [
            'identificacao' => ['label' => __('Dados pessoais'), 'icon' => 'fa-id-card', 'fields' => ['full_name', 'social_name', 'cpf', 'birth_date', 'birth_city', 'birth_state', 'nationality', 'student_inep', 'nis', 'receives_federal_aid', 'mother_name', 'father_name']],
            'contatos' => ['label' => __('Contatos'), 'icon' => 'fa-address-book', 'fields' => ['institutional_email', 'personal_email', 'phone']],
            'endereco' => ['label' => __('Endereço'), 'icon' => 'fa-map-marker-alt', 'fields' => ['address', 'number', 'district', 'city', 'state', 'postal_code', 'address_complement']],
            'socioeconomica' => ['label' => __('Ficha socioeconômica'), 'icon' => 'fa-hand-holding-heart', 'fields' => ['socioeconomic_data', 'socioeconomic_data.*']],
        ];
        $defaultProfileTab = array_key_exists(old('profile_tab', ''), $profileTabs) ? old('profile_tab') : 'identificacao';
        foreach ($profileTabs as $key => $tab) {
            if (collect($tab['fields'])->contains(fn ($field) => $errors->has($field))) {
                $defaultProfileTab = $key;
                break;
            }
        }
    @endphp
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="text-gray-700">{{ __('screens.confirm_personal_data') }}</p>

            <form method="POST" action="{{ route('profile.update') }}" data-profile-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="profile_tab" value="{{ $defaultProfileTab }}">
                <nav class="sge-section-nav sge-academic-tabs sge-profile-tabs mb-4" aria-label="{{ __('Seções do meu cadastro') }}" role="tablist" data-section-tabs data-default-tab="{{ $defaultProfileTab }}">
                    @foreach($profileTabs as $key => $tab)
                    <button type="button" id="profile-tab-{{ $key }}" class="sge-section-nav-item {{ $defaultProfileTab === $key ? 'is-active' : '' }}" role="tab" data-academic-tab="{{ $key }}" aria-controls="profile-panel-{{ $key }}" aria-selected="{{ $defaultProfileTab === $key ? 'true' : 'false' }}" tabindex="{{ $defaultProfileTab === $key ? '0' : '-1' }}"><i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i><span>{{ $tab['label'] }}</span></button>
                    @endforeach
                </nav>

                @include('people._form', [
                    'person' => $person,
                    'lockInstitutionalEmail' => $lockInstitutionalEmail ?? true,
                    'lockOwnIdentity' => $lockOwnIdentity ?? true,
                    'showActiveControl' => false,
                    'useProfileTabs' => true,
                ])

                <button class="btn btn-primary" type="submit">{{ __('screens.save_continue') }}</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('template/js/sge-profile-tabs.js') }}?v={{ filemtime(public_path('template/js/sge-profile-tabs.js')) }}"></script>
@endpush
