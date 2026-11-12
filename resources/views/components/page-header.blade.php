@props(['title'])

<header class="sge-page-header">
    <div class="sge-page-heading">
        <h1 class="h3 mb-0 text-gray-800">{{ $title }}</h1>
        @hasSection('page-subtitle')<small class="sge-area-subtitle">@yield('page-subtitle')</small>@endif
    </div>
    @if (isset($actions) && trim((string) $actions) !== '')
        <div class="d-flex align-items-center sge-page-actions">
            {{ $actions }}
        </div>
    @endif
</header>
