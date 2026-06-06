<?php

namespace App\Livewire;

use App\Models\School;
use App\Support\BrazilianStates;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

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

    public function filters(): array
    {
        return [
            SelectFilter::make('Situação')
                ->options([
                    '' => 'Todas',
                    '1' => 'Ativas',
                    '0' => 'Inativas',
                ])
                ->filter(fn (Builder $builder, string $value) => $builder->where('active', $value === '1')),
            SelectFilter::make('UF')
                ->options(['' => 'Todas'] + array_combine(BrazilianStates::codes(), BrazilianStates::codes()))
                ->filter(fn (Builder $builder, string $value) => $builder->where('state', $value)),
        ];
    }
}
