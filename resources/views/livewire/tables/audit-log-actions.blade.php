<a class="btn btn-sm btn-primary sge-icon-action" href="{{ route((int) $auditLog->group_count > 1 ? 'audit-logs.group' : 'audit-logs.show', $auditLog) }}" aria-label="{{ (int) $auditLog->group_count > 1 ? __('Ver :count registros agrupados', ['count' => $auditLog->group_count]) : __('screens.open_audit', ['id' => $auditLog->id]) }}" title="{{ __('screens.open_details') }}">
    <i class="fas fa-folder-open" aria-hidden="true"></i>
</a>
