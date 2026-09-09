<details class="sge-alert-entry" id="{{ $modalId }}">
    <summary>
        <span class="sge-alert-preview-icon"><i class="fas {{ $alertUrl ? 'fa-book-open' : 'fa-bullhorn' }}" aria-hidden="true"></i></span>
        <span class="sge-alert-preview-copy">
            <span class="sge-alert-preview-context">{{ $alertContext }}</span>
            <strong>{{ $alertTitle }}</strong>
            <span class="sge-alert-preview-message">{{ \Illuminate\Support\Str::limit($alertBody, 140) }}</span>
        </span>
        <i class="fas fa-chevron-down sge-alert-preview-arrow" aria-hidden="true"></i>
    </summary>
    <div class="sge-alert-entry-body">
        <p class="text-muted small">
            @if ($alertAuthor)<span class="d-block">{{ __('alerts.sent_by') }} {{ $alertAuthor }}</span>@endif
            @if ($alertDate)<span class="d-block">{{ $alertDate->timezone(auth()->user()->auditTimezone())->format('d/m/Y H:i') }}</span>@endif
        </p>
        <div class="sge-alert-full-message">{{ $alertBody }}</div>
        @if ($alertUrl)<a class="btn btn-primary mt-3" href="{{ $alertUrl }}">{{ __('alerts.open_diary') }}</a>@endif
    </div>
</details>
