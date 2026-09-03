<?php

namespace App\Livewire;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class PeopleTable extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['people.id as id']);
        $this->setDefaultSort('full_name');
        $this->setSearchDebounce(250);
        $this->setOfflineIndicatorDisabled();
    }

    public function builder(): Builder
    {
        $user = auth()->user();
        $roleFilter = $this->currentFilterValue('papel');
        $schoolFilter = $this->currentFilterValue('escola');
        $roleStatusFilter = $this->currentFilterValue('vinculo');

        return Person::query()
            ->with('schoolRoles.school')
            ->when(! $user->isAdministrator(), function (Builder $query) use ($user): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $user->manageableSchoolIds()));
            })
            ->when(filled($roleFilter) || filled($schoolFilter) || filled($roleStatusFilter), function (Builder $query) use ($roleFilter, $schoolFilter, $roleStatusFilter): void {
                $query->whereHasSchoolRole($roleFilter ?: null, $schoolFilter ?: null, $roleStatusFilter ?: null);
            });
    }

    public function columns(): array
    {
        return [
            Column::make(__('screens.name'), 'full_name')->sortable()->searchable(),
            Column::make(__('screens.institutional_email'), 'institutional_email')->sortable()->searchable(),
            Column::make('CPF', 'cpf')->searchable(),
            Column::make(__('screens.relationships'))
                ->label(fn (Person $row): string => view('livewire.tables.person-roles', ['person' => $row])->render())
                ->html(),
            Column::make(__('screens.status'), 'active')
                ->label(fn (Person $row): string => $row->hasActiveRoleForDate() ? __('screens.active_f_singular') : __('screens.inactive_f_singular')),
            Column::make(__('screens.actions'))
                ->label(fn (Person $row): string => view('livewire.tables.person-actions', ['person' => $row])->render())
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
            SelectFilter::make(__('screens.status'))
                ->options([
                    '' => __('screens.all_f'),
                    '1' => __('screens.active_f'),
                    '0' => __('screens.inactive_f'),
                ])
                ->filter(fn (Builder $builder, string $value) => $value === '1'
                    ? $builder->whereActiveByRoles()
                    : $builder->whereInactiveByRoles()),
            SelectFilter::make(__('screens.role'))
                ->options(['' => __('screens.all_m')] + collect(array_keys(PersonSchoolRole::ROLE_LABELS))->mapWithKeys(fn (string $role): array => [$role => __('roles.roles.'.$role)])->all())
                ->filter(fn (Builder $builder, string $value) => $builder),
            SelectFilter::make(__('screens.school'))
                ->options(['' => __('screens.all_f')] + $schools)
                ->filter(fn (Builder $builder, string $value) => $builder),
            SelectFilter::make(__('screens.relationship'))
                ->options([
                    '' => __('screens.all_m'),
                    'ativos' => __('screens.active_today'),
                    'inativos' => __('screens.inactive_or_closed'),
                    'sem' => __('screens.without_relationship'),
                ])
                ->filter(fn (Builder $builder, string $value) => $builder),
        ];
    }

    private function currentFilterValue(string $key): mixed
    {
        return $this->getAppliedFilterWithValue($key) ?? request()->input("table-filters.{$key}");
    }
}
