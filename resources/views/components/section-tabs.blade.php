@props(['tabs', 'label', 'defaultTab' => null, 'idPrefix' => 'section'])

@php
    $selectedTab = array_key_exists($defaultTab ?? '', $tabs) ? $defaultTab : array_key_first($tabs);
    foreach ($tabs as $key => $tab) {
        if (collect($tab['fields'] ?? [])->contains(fn ($field) => $errors->has($field))) {
            $selectedTab = $key;
            break;
        }
    }
@endphp

<nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ $label }}" role="tablist" data-section-tabs data-default-tab="{{ $selectedTab }}">
    @foreach ($tabs as $key => $tab)
        <a id="{{ $idPrefix }}-tab-{{ $key }}" href="#{{ $tab['panel'] ?? 'section-'.$key }}" class="sge-section-nav-item" data-academic-tab="{{ $key }}" role="tab" aria-controls="{{ $tab['panel'] ?? 'section-'.$key }}">
            <i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i>
            <span>{{ $tab['label'] }}</span>
            @isset($tab['count'])
                <small>{{ trans_choice(':count registro|:count registros', $tab['count'], ['count' => $tab['count']]) }}</small>
            @endisset
        </a>
    @endforeach
</nav>
