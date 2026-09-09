<div class="modal fade sge-alert-detail-modal" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}-title">{{ $alertTitle }}</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('alerts.close') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <strong>{{ $alertContext }}</strong>
                    @if ($alertAuthor)<span class="d-block">{{ __('alerts.sent_by') }} {{ $alertAuthor }}</span>@endif
                    @if ($alertDate)<span class="d-block">{{ $alertDate->timezone(auth()->user()->auditTimezone())->format('d/m/Y H:i') }}</span>@endif
                </p>
                <div style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $alertBody }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('alerts.close') }}</button>
                @if ($alertUrl)<a class="btn btn-primary" href="{{ $alertUrl }}">{{ __('alerts.open_diary') }}</a>@endif
            </div>
        </div>
    </div>
</div>
