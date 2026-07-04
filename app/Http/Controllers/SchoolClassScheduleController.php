<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassScheduleSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolClassScheduleController extends Controller
{
    public function index(Request $request, AcademicYear $academicYear, SchoolClass $class): View
    {
        $this->authorize($request, $academicYear, $class);

        $schedules = $class->schedules()
            ->with(['slots.componentAssignment.component.course', 'slots.componentAssignment.teacher'])
            ->orderByDesc('starts_at')
            ->get();
        $schedule = $request->filled('schedule')
            ? $schedules->firstWhere('id', $request->integer('schedule'))
            : $schedules->first();
        $assignments = $class->componentAssignments()
            ->with(['component.area', 'component.course', 'teacher'])
            ->where('active', true)
            ->get()
            ->sortBy(fn ($assignment) => ($assignment->component?->area?->name ?? '').' '.$assignment->component?->name);

        return view('school-class-schedules.index', [
            'academicYear' => $academicYear->load('school'),
            'class' => $class->load('courses'),
            'schedules' => $schedules,
            'schedule' => $schedule,
            'assignments' => $assignments,
            'classMinutes' => $this->classMinutes($academicYear, $class),
            'weekdays' => $this->weekdays($academicYear),
            'assignmentUsage' => $schedule ? $this->assignmentUsage($schedule) : collect(),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear, SchoolClass $class): RedirectResponse
    {
        $this->authorize($request, $academicYear, $class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$class->starts_at->toDateString(), 'before_or_equal:'.$class->ends_at->toDateString()],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$class->ends_at->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $effectiveEnd = $data['ends_at'] ?? $class->ends_at->toDateString();
        $overlaps = $class->schedules()
            ->whereDate('starts_at', '<=', $effectiveEnd)
            ->where(function ($query) use ($data): void {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $data['starts_at']);
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_at' => 'A vigência deste horário se sobrepõe a outra versão cadastrada para a turma.']);
        }

        $schedule = $class->schedules()->create($data);

        return redirect()->route('academic-years.classes.schedules.index', [$academicYear, $class, 'schedule' => $schedule->id])
            ->with('status', 'Versão de horário cadastrada. Agora inclua os blocos semanais.');
    }

    public function storeSlot(Request $request, AcademicYear $academicYear, SchoolClass $class, SchoolClassSchedule $schedule): RedirectResponse
    {
        $this->authorize($request, $academicYear, $class);
        abort_unless($schedule->school_class_id === $class->id, 404);

        $schedule->slots()->create($this->validatedSlotData($request, $academicYear, $class, $schedule));

        return back()->with('status', 'Bloco incluído no horário.');
    }

    public function updateSlot(Request $request, AcademicYear $academicYear, SchoolClass $class, SchoolClassSchedule $schedule, SchoolClassScheduleSlot $slot): RedirectResponse
    {
        $this->authorize($request, $academicYear, $class);
        abort_unless($schedule->school_class_id === $class->id && $slot->school_class_schedule_id === $schedule->id, 404);

        $slot->update($this->validatedSlotData($request, $academicYear, $class, $schedule, $slot));

        return back()->with('status', 'Bloco atualizado no horário.');
    }

    public function destroySlot(Request $request, AcademicYear $academicYear, SchoolClass $class, SchoolClassSchedule $schedule, SchoolClassScheduleSlot $slot): RedirectResponse
    {
        $this->authorize($request, $academicYear, $class);
        abort_unless($schedule->school_class_id === $class->id && $slot->school_class_schedule_id === $schedule->id, 404);
        $slot->delete();

        return back()->with('status', 'Bloco removido do horário.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, SchoolClass $class, SchoolClassSchedule $schedule): RedirectResponse
    {
        $this->authorize($request, $academicYear, $class);
        abort_unless($schedule->school_class_id === $class->id, 404);
        $schedule->delete();

        return redirect()->route('academic-years.classes.schedules.index', [$academicYear, $class])
            ->with('status', 'Versão de horário removida.');
    }

    private function authorize(Request $request, AcademicYear $academicYear, SchoolClass $class): void
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        if ($academicYear->isClosed() && ! $request->isMethod('GET')) {
            throw ValidationException::withMessages([
                'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar horários.',
            ]);
        }
    }

    private function validatedSlotData(Request $request, AcademicYear $academicYear, SchoolClass $class, SchoolClassSchedule $schedule, ?SchoolClassScheduleSlot $slot = null): array
    {
        $assignmentIds = $class->componentAssignments()->where('active', true)->pluck('id')->all();
        $data = $request->validate([
            'weekday' => ['required', 'integer', Rule::in(array_keys($this->weekdays($academicYear)))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'type' => ['required', Rule::in(array_keys(SchoolClassScheduleSlot::TYPE_LABELS))],
            'school_class_component_id' => ['nullable', Rule::in($assignmentIds)],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['starts_at'] < '06:00' || $data['ends_at'] > '22:00') {
            throw ValidationException::withMessages(['starts_at' => 'Os blocos devem estar entre 06:00 e 22:00.']);
        }

        if ($data['type'] === SchoolClassScheduleSlot::TYPE_CLASS) {
            if (empty($data['school_class_component_id'])) {
                throw ValidationException::withMessages(['school_class_component_id' => 'Selecione o componente curricular da aula.']);
            }

            $data['label'] = null;
            $this->ensureAssignmentFitsSchedule($schedule, (int) $data['school_class_component_id']);
            $this->ensureWeeklyLessonLimit($schedule, (int) $data['school_class_component_id'], $slot);
        } else {
            $data['label'] = blank($data['label']) ? 'Intervalo' : $data['label'];
            $data['school_class_component_id'] = null;
        }

        if ($this->overlaps($schedule, (int) $data['weekday'], $data['starts_at'], $data['ends_at'], $slot)) {
            throw ValidationException::withMessages(['starts_at' => 'Este bloco se sobrepõe a outro horário do mesmo dia.']);
        }

        return $data;
    }

    private function ensureWeeklyLessonLimit(SchoolClassSchedule $schedule, int $assignmentId, ?SchoolClassScheduleSlot $slot = null): void
    {
        $assignment = $schedule->schoolClass
            ->componentAssignments()
            ->with('component')
            ->whereKey($assignmentId)
            ->firstOrFail();
        $weeklyLessons = $assignment->component?->weekly_lessons;

        if ($weeklyLessons === null) {
            throw ValidationException::withMessages([
                'school_class_component_id' => 'Defina a quantidade de aulas semanais deste componente antes de montar o horário.',
            ]);
        }

        $currentCount = $schedule->slots()
            ->when($slot, fn ($query) => $query->whereKeyNot($slot->id))
            ->where('type', SchoolClassScheduleSlot::TYPE_CLASS)
            ->where('school_class_component_id', $assignmentId)
            ->count();

        if ($currentCount + 1 > (int) $weeklyLessons) {
            throw ValidationException::withMessages([
                'school_class_component_id' => 'Este componente já atingiu o limite de '.$weeklyLessons.' aula(s) semanal(is) na matriz.',
            ]);
        }
    }

    private function ensureAssignmentFitsSchedule(SchoolClassSchedule $schedule, int $assignmentId): void
    {
        $assignment = $schedule->schoolClass
            ->componentAssignments()
            ->with('component.startsPeriod', 'component.endsPeriod', 'component.course.startsPeriod', 'component.course.endsPeriod')
            ->whereKey($assignmentId)
            ->firstOrFail();

        $component = $assignment->component;
        $startsAt = ($component?->startsPeriod ?? $component?->course?->startsPeriod)?->starts_at;
        $endsAt = ($component?->endsPeriod ?? $component?->course?->endsPeriod)?->ends_at;

        if ($startsAt && $schedule->starts_at->lt($startsAt->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'school_class_component_id' => 'Este componente começa depois do início desta versão de horário.',
            ]);
        }

        $scheduleEndsAt = ($schedule->ends_at ?? $schedule->schoolClass->ends_at)->copy()->startOfDay();
        if ($endsAt && $scheduleEndsAt->gt($endsAt->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'school_class_component_id' => 'Este componente termina antes do fim desta versão de horário.',
            ]);
        }
    }

    private function overlaps(SchoolClassSchedule $schedule, int $weekday, string $startsAt, string $endsAt, ?SchoolClassScheduleSlot $ignoreSlot = null): bool
    {
        return $schedule->slots()
            ->when($ignoreSlot, fn ($query) => $query->whereKeyNot($ignoreSlot->id))
            ->where('weekday', $weekday)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    private function classMinutes(AcademicYear $academicYear, SchoolClass $class): int
    {
        return (int) ($class->courses->pluck('class_hour_minutes')->filter()->first() ?? $academicYear->class_hour_minutes ?? 50);
    }

    /**
     * @return array<int, string>
     */
    private function weekdays(AcademicYear $academicYear): array
    {
        $weekdays = SchoolClassScheduleSlot::WEEKDAY_LABELS;

        if (! $academicYear->days()
            ->where('counts_as_school_day', true)
            ->whereRaw($this->saturdayExpression())
            ->exists()) {
            unset($weekdays[6]);
        }

        return $weekdays;
    }

    private function saturdayExpression(): string
    {
        return config('database.default') === 'sqlite'
            ? "strftime('%w', date) = '6'"
            : 'DAYOFWEEK(date) = 7';
    }

    private function assignmentUsage(SchoolClassSchedule $schedule): Collection
    {
        return $schedule->slots
            ->where('type', SchoolClassScheduleSlot::TYPE_CLASS)
            ->whereNotNull('school_class_component_id')
            ->countBy('school_class_component_id');
    }
}
