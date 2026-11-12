<?php

namespace App\Livewire;

use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Support\HistoryCurriculumCatalog;
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
        $this->setAdditionalSelects(['history_curriculum_components.id as id', 'history_curriculum_components.knowledge_area_id', 'history_curriculum_components.matrix_origin']);
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

        return HistoryCurriculumComponent::query()->with(['area', 'matrixComponents.course.academicYear.school'])->withCount('historyComponents');
    }

    public function columns(): array
    {
        return [
            Column::make(__('Componente'), 'name')->sortable()->searchable(),
            Column::make(__('Área'), 'area.name')->sortable()->searchable()
                ->format(fn ($value, $row) => view('partials.knowledge-area-label', ['labelArea' => $row->area, 'labelName' => $value])->render())->html(),
            Column::make(__('Formação'), 'formation')->sortable()->format(fn ($value) => __($value)),
            Column::make(__('Etapa'), 'education_stage')->sortable()->format(fn ($value) => __(HistoryCurriculumCatalog::stageLabels()[$value])),
            Column::make(__('Origem'))->label(fn (HistoryCurriculumComponent $row): string => $row->isMatrixDerived() ? __('Matriz do sistema') : __('Histórico / cadastro')),
            Column::make(__('Históricos'))->label(fn (HistoryCurriculumComponent $row): string => view('livewire.tables.history-curriculum-count', ['component' => $row])->render())->html(),
            Column::make(__('Ações'))->label(fn (HistoryCurriculumComponent $row): string => view('livewire.tables.history-curriculum-actions', ['component' => $row])->render())->html(),
        ];
    }

    public function filters(): array
    {
        $stages = HistoryCurriculumCatalog::stageLabels();

        return [
            SelectFilter::make(__('Etapa'), 'education_stage')
                ->options(['' => __('Todas')] + array_map(fn ($label) => __($label), $stages))
                ->filter(fn (Builder $query, string $value) => $query->where('history_curriculum_components.education_stage', $value)),
            SelectFilter::make(__('Área'), 'area_id')
                ->options(['' => __('Todas')] + KnowledgeArea::orderBy('name')->get()->mapWithKeys(fn ($area) => [$area->id => $area->selectionLabel()])->all())
                ->filter(fn (Builder $query, string $value) => $query->where('history_curriculum_components.knowledge_area_id', (int) $value)),
        ];
    }

    public function customView(): string
    {
        return 'livewire.tables.history-curriculum-modals';
    }
}
