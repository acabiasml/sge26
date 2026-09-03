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
        $user = auth()->user();

        return School::query()
            ->when($user && ! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $user->manageableSchoolIds()));
    }

    public function columns(): array
    {
        return [
            Column::make(__('screens.name'), 'name')->sortable()->searchable(),
            Column::make(__('screens.city'), 'city')->sortable()->searchable(),
            Column::make('UF', 'state')->sortable(),
            Column::make('CNPJ', 'cnpj')
                ->format(fn ($value, School $row): string => $row->formattedCnpj())
                ->sortable()
                ->searchable(),
            Column::make(__('screens.actions'))
                ->label(fn (School $row): string => view('livewire.tables.school-actions', ['school' => $row])->render())
                ->html(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make(__('screens.status'))
                ->options([
                    '' => __('screens.all_f'),
                    '1' => __('screens.active_f'),
                    '0' => __('screens.inactive_f'),
                ])
                ->filter(fn (Builder $builder, string $value) => $builder->where('active', $value === '1')),
            SelectFilter::make('UF')
                ->options(['' => __('screens.all_f')] + array_combine(BrazilianStates::codes(), BrazilianStates::codes()))
                ->filter(fn (Builder $builder, string $value) => $builder->where('state', $value)),
        ];
    }
}
