<?php

namespace App\Livewire;

use App\Models\AcademicPeriod;
use App\Models\CurriculumComponent;
use App\Models\DiaryAlert;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class DiaryAlertManager extends Component
{
    #[Locked]
    public int $schoolClassId = 0;

    #[Locked]
    public int $componentId = 0;

    #[Locked]
    public int $periodId = 0;

    #[Locked]
    public ?int $editingAlertId = null;

    #[Locked]
    public ?int $deletingAlertId = null;

    public string $message = '';

    public string $notice = '';

    #[On('manage-diary-alerts')]
    public function open(int $schoolClassId, int $componentId, int $periodId): void
    {
        $this->schoolClassId = $schoolClassId;
        $this->componentId = $componentId;
        $this->periodId = $periodId;
        $this->context();
        $this->cancelEdit();
        $this->deletingAlertId = null;
        $this->notice = '';
        $this->dispatch('diary-alert-manager-open');
    }

    public function edit(int $id): void
    {
        $alert = $this->ownAlert($id);
        $this->editingAlertId = $alert->id;
        $this->message = $alert->message;
        $this->deletingAlertId = null;
        $this->resetValidation();
        $this->notice = '';
        $this->dispatch('diary-alert-manager-focus');
    }

    public function cancelEdit(): void
    {
        $this->editingAlertId = null;
        $this->message = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        [$class, $component, $period] = $this->context();
        $this->message = trim($this->message);
        $this->validate(['message' => ['required', 'string', 'max:2000']], [], ['message' => __('Mensagem do alerta')]);
        if ($this->editingAlertId !== null) {
            $this->ownAlert($this->editingAlertId)->update(['message' => $this->message]);
            $this->notice = __('Alerta atualizado.');
        } else {
            if ($class->academicYear->isReadOnly()) {
                throw ValidationException::withMessages(['message' => __('Este ano letivo está fechado. Reabra o ano letivo antes de alterar diários.')]);
            }
            if (! $component->isActiveInPeriod($period)) {
                throw ValidationException::withMessages(['message' => __('Este componente curricular não está previsto para este período avaliativo.')]);
            }
            $assignment = SchoolClassComponent::query()->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->firstOrFail();
            if (! $assignment->teacher_person_id) {
                throw ValidationException::withMessages(['message' => __('Defina a docência deste diário antes de enviar um alerta.')]);
            }
            DiaryAlert::create([
                'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'from_person_id' => auth()->user()->person_id,
                'to_person_id' => $assignment->teacher_person_id, 'message' => $this->message,
            ]);
            $this->notice = __('Alerta enviado para a docência.');
        }
        $this->cancelEdit();
        $this->dispatch('diary-alerts-changed');
    }

    public function requestDelete(int $id): void
    {
        $this->deletingAlertId = $this->ownAlert($id)->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingAlertId = null;
    }

    public function delete(): void
    {
        abort_unless($this->deletingAlertId !== null, 422);
        $alert = $this->ownAlert($this->deletingAlertId);
        if ($this->editingAlertId === $alert->id) {
            $this->cancelEdit();
        }
        $alert->delete();
        $this->deletingAlertId = null;
        $this->notice = __('Alerta excluído.');
        $this->dispatch('diary-alerts-changed');
    }

    private function ownAlert(int $id): DiaryAlert
    {
        $this->context();
        $alert = $this->alerts()->find($id);
        abort_unless($alert, 404);
        abort_unless($alert->canBeManagedBy(auth()->user()), 403);

        return $alert;
    }

    private function alerts(): Builder
    {
        return DiaryAlert::query()->where('school_class_id', $this->schoolClassId)
            ->where('curriculum_component_id', $this->componentId)->where('academic_period_id', $this->periodId);
    }

    /** @return array{SchoolClass, CurriculumComponent, AcademicPeriod} */
    private function context(): array
    {
        abort_unless(auth()->check(), 403);
        $class = SchoolClass::with('academicYear')->findOrFail($this->schoolClassId);
        abort_unless($class->academicYear && auth()->user()->canManageSchool($class->academicYear->school_id), 403);
        $component = CurriculumComponent::with('course')->findOrFail($this->componentId);
        abort_unless($component->course?->academic_year_id === $class->academic_year_id, 404);
        abort_unless($class->courses()->whereKey($component->academic_course_id)->exists(), 404);
        $period = AcademicPeriod::where('academic_year_id', $class->academic_year_id)->findOrFail($this->periodId);

        return [$class, $component, $period];
    }

    public function render(): View
    {
        $context = $this->schoolClassId ? $this->context() : null;

        return view('livewire.diary-alert-manager', [
            'context' => $context,
            'alerts' => $context ? $this->alerts()->with(['fromPerson', 'toPerson', 'schoolClass.academicYear'])->latest('id')->get() : collect(),
        ]);
    }
}
