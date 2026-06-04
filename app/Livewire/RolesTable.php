<?php

namespace App\Livewire;

use App\Models\PersonSchoolRole;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

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
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()));
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
}
