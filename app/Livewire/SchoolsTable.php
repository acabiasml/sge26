<?php

namespace App\Livewire;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class SchoolsTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['schools.id as id']);
        $this->setDefaultSort('name');
        $this->setOfflineIndicatorDisabled();
    }

    public function builder(): Builder
    {
        return School::query();
    }

    public function columns(): array
    {
        return [
            Column::make('Nome', 'name')->sortable()->searchable(),
            Column::make('Cidade', 'city')->sortable()->searchable(),
            Column::make('UF', 'state')->sortable(),
            Column::make('INEP', 'inep')->sortable()->searchable(),
            Column::make('Situação', 'active')
                ->format(fn (bool $value): string => $value ? 'Ativa' : 'Inativa')
                ->sortable(),
            Column::make('Ações')
                ->label(fn (School $row): string => view('livewire.tables.school-actions', ['school' => $row])->render())
                ->html(),
        ];
    }
}
