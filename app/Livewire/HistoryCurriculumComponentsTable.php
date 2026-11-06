<?php

namespace App\Livewire;

use App\Models\AcademicCourse;
use App\Models\HistoryCurriculumComponent;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class HistoryCurriculumComponentsTable extends DataTableComponent
{
    public function configure(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['history_curriculum_components.id as id', 'history_curriculum_components.knowledge_area_id']);
        $this->setDefaultSort('name');
        $this->setSearchDebounce(250);
        $this->setOfflineIndicatorDisabled();
        $this->setPerPageAccepted([10]);
        $this->setPerPageVisibilityDisabled();
        $this->setTableAttributes(['aria-label' => __('Catálogo de componentes')]);
    }

    public function builder(): Builder
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        return HistoryCurriculumComponent::query()->with('area');
    }

    public function columns(): array
    {
        return [
            Column::make(__('Componente'), 'name')->sortable()->searchable(),
            Column::make(__('Área'), 'area.name')->sortable()->searchable(),
            Column::make(__('Formação'), 'formation')->sortable()->format(fn ($value) => __($value)),
            Column::make(__('Etapa'), 'education_stage')->sortable()->format(fn ($value) => __(AcademicCourse::STAGE_LABELS[$value])),
            Column::make(__('Situação'), 'active')->sortable()->format(fn ($value) => $value ? __('Ativo') : __('Inativo')),
            Column::make(__('Ações'))->label(fn (HistoryCurriculumComponent $row): string => view('livewire.tables.history-curriculum-actions', ['component' => $row])->render())->html(),
        ];
    }

    public function filters(): array
    {
        $stages = array_intersect_key(AcademicCourse::STAGE_LABELS, array_flip(['fundamental', 'medio', 'tecnico']));

        return [
            SelectFilter::make(__('Etapa'), 'education_stage')
                ->options(['' => __('Todas')] + array_map(fn ($label) => __($label), $stages))
                ->filter(fn (Builder $query, string $value) => $query->where('history_curriculum_components.education_stage', $value)),
            SelectFilter::make(__('Situação'), 'status')
                ->options(['' => __('Todas'), '1' => __('Ativo'), '0' => __('Inativo')])
                ->filter(fn (Builder $query, string $value) => $query->where('history_curriculum_components.active', $value === '1')),
        ];
    }

    public function customView(): string
    {
        return 'livewire.tables.history-curriculum-modals';
    }
}
