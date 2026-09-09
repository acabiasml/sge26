<div class="modal fade" id="alertsCenter" tabindex="-1" role="dialog" aria-labelledby="alertsCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable sge-alert-center-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title h5" id="alertsCenterTitle">{{ __('navigation.alerts') }} <span class="sge-alert-panel-count">{{ $topbarAlertCount }}</span></h2>
                    <p class="text-muted small mb-0 mt-2">{{ __('alerts.preview_hint') }}</p></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('alerts.close') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body sge-alert-center-list">
@foreach ($topbarAnnouncements as $announcement)
    @include('layouts.partials.alert-entry', [
        'modalId' => 'announcement-alert-'.$announcement->id,
        'alertTitle' => $announcement->title,
        'alertContext' => $announcement->school?->name ?? __('navigation.global'),
        'alertBody' => $announcement->body,
        'alertDate' => $announcement->starts_at,
        'alertAuthor' => null,
        'alertUrl' => null,
    ])
@endforeach
@foreach ($topbarDiaryAlerts as $alert)
    @include('layouts.partials.alert-entry', [
        'modalId' => 'diary-alert-'.$alert->id,
        'alertTitle' => __('navigation.management_alert', ['component' => $alert->component?->name]),
        'alertContext' => collect([$alert->schoolClass?->name, $alert->period?->name])->filter()->join(' · '),
        'alertBody' => $alert->message,
        'alertDate' => $alert->created_at,
        'alertAuthor' => $alert->fromPerson?->full_name,
        'alertUrl' => route('teacher-diaries.show', [$alert->schoolClass, $alert->component, 'period' => $alert->academic_period_id]),
    ])
@endforeach
                @if ($topbarAlertCount === 0)
                    <div class="sge-alert-panel-empty"><i class="far fa-bell" aria-hidden="true"></i><p>{{ __('navigation.no_active_announcement') }}</p></div>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('alerts.close') }}</button></div>
        </div>
    </div>
</div>
