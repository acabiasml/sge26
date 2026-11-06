<?php

namespace App\Livewire;

use App\Models\HistoryCurriculumComponent;
use App\Models\StudentAcademicHistoryComponent;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class HistoryCurriculumUsagesTable extends DataTableComponent
{
    #[Locked]
    public int $catalogId;

    public function mount(int $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function configure(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['student_academic_history_components.id as id', 'student_academic_history_components.student_academic_history_id']);
        $this->setDefaultSort('history.student.full_name');
        $this->setSearchDebounce(250);
        $this->setOfflineIndicatorDisabled();
        $this->setPerPageAccepted([10]);
        $this->setPerPageVisibilityDisabled();
    }

    public function builder(): Builder
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        HistoryCurriculumComponent::findOrFail($this->catalogId);

        return StudentAcademicHistoryComponent::whereHas('catalogEntries', fn ($query) => $query->whereKey($this->catalogId))
            ->with(['history.student', 'history.school']);
    }

    public function columns(): array
    {
        return [
            Column::make(__('Estudante'), 'history.student.full_name')->sortable()->searchable(),
            Column::make(__('Escola'), 'history.school.name')->sortable()->searchable(),
            Column::make(__('Componente'), 'name')->sortable()->searchable(),
            Column::make(__('Ações'))->label(fn (StudentAcademicHistoryComponent $row): string => view('livewire.tables.history-curriculum-usage-actions', ['component' => $row])->render())->html(),
        ];
    }
}
