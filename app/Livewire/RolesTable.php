<?php

namespace App\Livewire;

use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class RolesTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['person_school_roles.id as id']);
        $this->setDefaultSort('role');
        $this->setOfflineIndicatorDisabled();
    }

    public function builder(): Builder
    {
        $user = auth()->user();

        return PersonSchoolRole::query()
            ->with(['person', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('person_school_roles.school_id', $user->manageableSchoolIds()));
    }

    public function columns(): array
    {
        return [
            Column::make('Pessoa', 'person.full_name')->sortable()->searchable(),
            Column::make('Papel', 'role')
                ->format(fn (string $value, PersonSchoolRole $row): string => $row->label())
                ->sortable(),
            Column::make('Escola', 'school.name')->sortable()->searchable(),
            Column::make('Início', 'started_at')
                ->format(fn ($value): string => $value ? $value->format('d/m/Y') : '-')
                ->sortable(),
            Column::make('Fim', 'ended_at')
                ->format(fn ($value): string => $value ? $value->format('d/m/Y') : 'Indeterminado')
                ->sortable(),
            Column::make('Situação', 'active')
                ->format(fn (bool $value, PersonSchoolRole $row): string => $row->isActiveForDate() ? 'Ativo' : 'Inativo')
                ->sortable(),
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
            SelectFilter::make('Papel')
                ->options(['' => 'Todos'] + PersonSchoolRole::ROLE_LABELS)
                ->filter(fn (Builder $builder, string $value) => $builder->where('person_school_roles.role', $value)),
            SelectFilter::make('Escola')
                ->options(['' => 'Todas', 'global' => 'Global'] + $schools)
                ->filter(fn (Builder $builder, string $value) => $value === 'global'
                    ? $builder->whereNull('person_school_roles.school_id')
                    : $builder->where('person_school_roles.school_id', (int) $value)),
            SelectFilter::make('Situação')
                ->options([
                    '' => 'Todas',
                    '1' => 'Ativos',
                    '0' => 'Inativos',
                ])
                ->filter(fn (Builder $builder, string $value) => $value === '1'
                    ? $this->activeForDate($builder)
                    : $this->inactiveForDate($builder)),
        ];
    }

    private function activeForDate(Builder $builder): Builder
    {
        return $builder
            ->where('person_school_roles.active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('person_school_roles.started_at')
                    ->orWhereDate('person_school_roles.started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('person_school_roles.ended_at')
                    ->orWhereDate('person_school_roles.ended_at', '>=', now()->toDateString());
            });
    }

    private function inactiveForDate(Builder $builder): Builder
    {
        return $builder->where(function (Builder $query): void {
            $query->where('person_school_roles.active', false)
                ->orWhereDate('person_school_roles.started_at', '>', now()->toDateString())
                ->orWhereDate('person_school_roles.ended_at', '<', now()->toDateString());
        });
    }
}
