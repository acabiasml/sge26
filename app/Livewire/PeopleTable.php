<?php

namespace App\Livewire;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class PeopleTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['people.id as id']);
        $this->setDefaultSort('full_name');
        $this->setOfflineIndicatorDisabled();
    }

    public function builder(): Builder
    {
        $user = auth()->user();

        return Person::query()
            ->with('schoolRoles.school')
            ->when(! $user->isAdministrator(), function (Builder $query) use ($user): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $user->manageableSchoolIds()));
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Nome', 'full_name')->sortable()->searchable(),
            Column::make('E-mail institucional', 'institutional_email')->sortable()->searchable(),
            Column::make('CPF', 'cpf')->searchable(),
            Column::make('Vínculos')
                ->label(fn (Person $row): string => view('livewire.tables.person-roles', ['person' => $row])->render())
                ->html(),
            Column::make('Situação', 'active')
                ->format(fn (bool $value): string => $value ? 'Ativa' : 'Inativa')
                ->sortable(),
            Column::make('Ações')
                ->label(fn (Person $row): string => view('livewire.tables.person-actions', ['person' => $row])->render())
                ->html(),
        ];
    }
}
