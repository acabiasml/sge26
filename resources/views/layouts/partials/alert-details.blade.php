@foreach ($topbarAnnouncements as $announcement)
    @include('layouts.partials.alert-modal', [
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
    @include('layouts.partials.alert-modal', [
        'modalId' => 'diary-alert-'.$alert->id,
        'alertTitle' => __('navigation.management_alert', ['component' => $alert->component?->name]),
        'alertContext' => collect([$alert->schoolClass?->name, $alert->period?->name])->filter()->join(' · '),
        'alertBody' => $alert->message,
        'alertDate' => $alert->created_at,
        'alertAuthor' => $alert->fromPerson?->full_name,
        'alertUrl' => route('teacher-diaries.show', [$alert->schoolClass, $alert->component, 'period' => $alert->academic_period_id]),
    ])
@endforeach
@push('scripts')
<script>
    $('.sge-alert-detail-modal').on('hidden.bs.modal', function () {
        document.getElementById('alertsDropdown')?.focus();
    });
</script>
@endpush
