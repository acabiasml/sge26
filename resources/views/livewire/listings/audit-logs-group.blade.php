<div>
<div class="card shadow mb-4"><div class="card-body"><div class="table-responsive">
    <table class="table"><thead><tr><th scope="col">{{ __('screens.when') }}</th><th scope="col">{{ __('screens.record') }}</th><th scope="col">{{ __('screens.details') }}</th></tr></thead><tbody>
    @foreach ($records as $record)
        <tr><td>{{ $record->created_at->timezone($auditTimezone)->format('d/m/Y H:i:s') }}</td><td>{{ \App\Support\AuditLogPresenter::recordLabel($record) }}</td><td>@include('livewire.tables.audit-log-actions', ['auditLog' => $record])</td></tr>
    @endforeach
    </tbody></table>
    </div>
    <div class="mt-3">{{ $records->links('livewire::bootstrap') }}</div>
</div></div>
</div>
