<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\School;
use App\Support\AuditLogPresenter;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

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
            Column::make(__('screens.when'), 'created_at')
                ->format(fn ($value): string => $value
                    ? $value->timezone($this->auditTimezone)->format('d/m/Y H:i').'<span class="d-block text-muted small">'.$this->auditTimezone.'</span>'
                    : '-')
                ->html()
                ->sortable(),
            Column::make(__('screens.who'))
                ->label(fn (AuditLog $row): string => e($row->actorPerson?->full_name ?? $row->actorUser?->name ?? __('screens.system')))
                ->html(),
            Column::make(__('screens.action'), 'action')
                ->format(fn (?string $value): string => AuditLogPresenter::actionLabel($value))
                ->sortable()
                ->searchable(),
            Column::make(__('screens.record'))
                ->label(fn (AuditLog $row): string => e(AuditLogPresenter::recordLabel($row)))
                ->html(),
            Column::make(__('screens.school'), 'school.name')->sortable()->searchable(),
            Column::make(__('screens.details'))
                ->label(fn (AuditLog $row): string => view('livewire.tables.audit-log-actions', ['auditLog' => $row])->render())
                ->html(),
        ];
    }

    public function filters(): array
    {
        $user = auth()->user();
        $schools = School::query()
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $user->manageableSchoolIds()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])
            ->all();

        return [
            SelectFilter::make(__('screens.action'))
                ->options([
                    '' => __('screens.all_f'),
                    'created' => __('screens.created'),
                    'updated' => __('screens.updated'),
                    'deleted' => __('screens.deleted'),
                ])
                ->filter(fn (Builder $builder, string $value) => $builder->where('action', $value)),
            SelectFilter::make(__('screens.school'))
                ->options(['' => __('screens.all_f'), 'global' => __('screens.global')] + $schools)
                ->filter(fn (Builder $builder, string $value) => $value === 'global'
                    ? $builder->whereNull('school_id')
                    : $builder->where('school_id', (int) $value)),
        ];
    }
}
