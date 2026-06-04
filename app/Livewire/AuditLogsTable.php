<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class AuditLogsTable extends DataTableComponent
{
    public string $auditTimezone = 'America/Sao_Paulo';

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects([
            'audit_logs.id as id',
            'audit_logs.actor_user_id',
            'audit_logs.actor_person_id',
            'audit_logs.school_id',
            'audit_logs.auditable_type',
            'audit_logs.auditable_id',
        ]);
        $this->setDefaultSort('created_at', 'desc');
        $this->setOfflineIndicatorDisabled();
    }

    public function builder(): Builder
    {
        $user = auth()->user();

        return AuditLog::query()
            ->with(['actorUser', 'actorPerson', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()));
    }

    public function columns(): array
    {
        return [
            Column::make('Quando', 'created_at')
                ->format(fn ($value): string => $value
                    ? $value->timezone($this->auditTimezone)->format('d/m/Y H:i').'<span class="d-block text-muted small">'.$this->auditTimezone.'</span>'
                    : '-')
                ->html()
                ->sortable(),
            Column::make('Quem')
                ->label(fn (AuditLog $row): string => e($row->actorPerson?->full_name ?? $row->actorUser?->name ?? 'Sistema'))
                ->html(),
            Column::make('Ação', 'action')->sortable()->searchable(),
            Column::make('Registro')
                ->label(fn (AuditLog $row): string => e(class_basename($row->auditable_type).' #'.$row->auditable_id))
                ->html(),
            Column::make('Escola', 'school.name')->sortable()->searchable(),
            Column::make('Detalhes')
                ->label(fn (AuditLog $row): string => view('livewire.tables.audit-log-actions', ['auditLog' => $row])->render())
                ->html(),
        ];
    }
}
