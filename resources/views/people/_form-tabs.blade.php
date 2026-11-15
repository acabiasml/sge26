@php
    $personFormTabs = [
        'identificacao' => ['label' => __('Dados pessoais'), 'icon' => 'fa-id-card', 'fields' => ['full_name', 'social_name', 'cpf', 'birth_date', 'birth_city', 'birth_state', 'nationality', 'student_inep', 'nis', 'receives_federal_aid', 'mother_name', 'father_name']],
        'contatos' => ['label' => __('Contatos'), 'icon' => 'fa-address-book', 'fields' => ['institutional_email', 'personal_email', 'phone']],
        'endereco' => ['label' => __('Endereço'), 'icon' => 'fa-map-marker-alt', 'fields' => ['address', 'number', 'district', 'city', 'state', 'postal_code', 'address_complement']],
        'socioeconomica' => ['label' => __('Ficha socioeconômica'), 'icon' => 'fa-hand-holding-heart', 'fields' => ['socioeconomic_data', 'socioeconomic_data.*']],
    ];
    foreach ($personFormTabs as $key => $tab) {
        $personFormTabs[$key]['panel'] = 'profile-panel-'.$key;
    }
    if ($includeInitialRole ?? false) {
        $personFormTabs['vinculo'] = ['label' => __('screens.initial_relationship'), 'icon' => 'fa-user-tag', 'fields' => ['initial_role', 'initial_school_id', 'initial_position', 'initial_started_at', 'initial_ended_at']];
    }
@endphp

<x-section-tabs :tabs="$personFormTabs" :label="__('Seções do cadastro da pessoa')" id-prefix="profile" />
