<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolClassScheduleSlot;
use Illuminate\Support\Str;

class AcademicStructureValidator
{
    /**
     * @return array<int, array{level:string,title:string,description:string,action_label?:string,action_url?:string}>
     */
    public static function forAcademicYear(AcademicYear $academicYear): array
    {
        $academicYear->loadMissing([
            'periods.assessmentRules',
            'days',
            'courses.components.area',
            'classes.courses.components',
            'classes.startsPeriod',
            'classes.endsPeriod',
            'classes.componentAssignments.teacher',
            'classes.enrollments',
        ]);

        $items = [];
        if ($academicYear->periods->isEmpty()) {
            $items[] = self::issue('danger', __('Nenhum período avaliativo cadastrado'), __('Cadastre os períodos para permitir avaliações, diários e fechamento por etapa.'), __('Gerenciar períodos'), route('academic-years.periods.index', $academicYear));
        }

        if ($academicYear->courses->isEmpty()) {
            $items[] = self::issue('danger', __('Nenhuma matriz cadastrada'), __('Crie ao menos uma matriz curricular antes de montar turmas.'), __('Nova matriz'), route('academic-years.courses.create', $academicYear));
        }

        foreach ($academicYear->courses as $course) {
            foreach (self::forCourse($course, false) as $issue) {
                $items[] = $issue;
            }
        }

        foreach ($academicYear->classes as $class) {
            foreach (self::forClass($class, false) as $issue) {
                $items[] = $issue;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{level:string,title:string,description:string,action_label?:string,action_url?:string}>
     */
    public static function forCourse(AcademicCourse $course, bool $load = true): array
    {
        if ($load) {
            $course->loadMissing('academicYear.periods', 'components.area', 'components.startsPeriod', 'components.endsPeriod', 'classes');
        }

        $items = [];
        $baseUrl = route('academic-years.courses.show', [$course->academic_year_id, $course]);

        if ($course->components->where('active', true)->isEmpty()) {
            $items[] = self::issue('danger', __('Matriz sem componentes ativos'), __('Inclua os componentes curriculares antes de criar turmas para esta matriz.'), __('Gerenciar matriz'), $baseUrl);
        }

        foreach ($course->components->where('active', true) as $component) {
            if (self::isBehaviorComponentName($component->name)) {
                continue;
            }

            if (blank($component->knowledge_area_id)) {
                $items[] = self::issue('warning', __('Componente sem área'), __(':component ainda não possui área do conhecimento.', ['component' => $component->name]), __('Abrir componente'), route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }

            if ((int) $component->weekly_lessons < 1 && (int) $component->workload_hours < 1) {
                $items[] = self::issue('warning', __('Componente sem carga horária'), __(':component precisa de aulas semanais ou carga horária total.', ['component' => $component->name]), __('Abrir componente'), route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }

            $starts = $component->startsPeriod;
            $ends = $component->endsPeriod;

            if ($starts && $ends && $starts->position > $ends->position) {
                $items[] = self::issue('danger', __('Duração inválida de componente'), __(':component começa depois do período final informado.', ['component' => $component->name]), __('Abrir componente'), route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }
        }

        if ($course->components->where('active', true)->isNotEmpty() && $course->classes->isEmpty()) {
            $items[] = self::issue('info', __('Matriz ainda sem turma'), __('A matriz está cadastrada, mas ainda não foi vinculada a nenhuma turma.'), __('Criar turma'), route('academic-years.classes.create', $course->academic_year_id));
        }

        return $items;
    }

    /**
     * @return array<int, array{level:string,title:string,description:string,action_label?:string,action_url?:string}>
     */
    public static function forClass(SchoolClass $class, bool $load = true): array
    {
        if ($load) {
            $class->loadMissing([
                'academicYear.periods',
                'startsPeriod',
                'endsPeriod',
                'courses.components',
                'componentAssignments.component.course',
                'componentAssignments.teacher',
                'enrollments',
                'schedules.slots.componentAssignment.component',
            ]);
        }

        $items = [];
        $academicYear = $class->academicYear;

        if (! $class->active) {
            $items[] = self::issue('warning', __('Turma inativa'), __('Turmas inativas não devem receber matrículas nem lançamentos de diário.'), __('Editar turma'), route('academic-years.classes.edit', [$academicYear, $class]));
        }

        if ($class->courses->isEmpty()) {
            $items[] = self::issue('danger', __('Turma sem matriz'), __('Vincule ao menos uma matriz curricular para gerar componentes, matrículas e diários.'), __('Editar turma'), route('academic-years.classes.edit', [$academicYear, $class]));
        }

        if ($class->startsPeriod && $class->endsPeriod && $class->startsPeriod->position > $class->endsPeriod->position) {
            $items[] = self::issue('danger', __('Duração inválida da turma'), __('O período inicial da turma está depois do período final.'), __('Editar turma'), route('academic-years.classes.edit', [$academicYear, $class]));
        }

        if ($class->enrollments->isEmpty()) {
            $items[] = self::issue('info', __('Turma sem matrículas'), __('Nenhum estudante foi matriculado nesta turma ainda.'), __('Gerenciar matrículas'), route('classes.enrollments.index', $class));
        }

        foreach ($class->componentAssignments->where('active', true) as $assignment) {
            if (self::isBehaviorComponentName($assignment->component?->name)) {
                continue;
            }

            if (blank($assignment->teacher_person_id)) {
                $items[] = self::issue('warning', __('Componente sem docência titular'), __(':component ainda não possui professor titular.', ['component' => $assignment->component?->name ?? __('Componente sem nome')]), __('Gerenciar turma'), route('academic-years.classes.show', [$academicYear, $class]));
            }
        }

        $scheduledCounts = $class->schedules
            ->flatMap(fn ($schedule) => $schedule->slots)
            ->filter(fn ($slot) => $slot->type === SchoolClassScheduleSlot::TYPE_CLASS && $slot->school_class_component_id)
            ->groupBy('school_class_component_id')
            ->map->count();

        foreach ($class->componentAssignments->where('active', true) as $assignment) {
            if (self::isBehaviorComponentName($assignment->component?->name)) {
                continue;
            }

            $expected = (int) ($assignment->component?->weekly_lessons ?? 0);

            if ($expected > 0 && $class->schedules->isNotEmpty()) {
                $scheduled = (int) ($scheduledCounts[$assignment->id] ?? 0);

                if ($scheduled === 0) {
                    $items[] = self::issue('warning', __('Componente fora do horário'), __(':component ainda não aparece em nenhum bloco de horário.', ['component' => $assignment->component?->name ?? __('Componente sem nome')]), __('Gerenciar horário'), route('academic-years.classes.schedules.index', [$academicYear, $class]));
                } elseif ($scheduled > $expected) {
                    $items[] = self::issue('danger', __('Horário acima das aulas semanais'), __(':component possui :scheduled bloco(s), mas a matriz prevê :expected.', ['component' => $assignment->component?->name ?? __('Componente sem nome'), 'scheduled' => $scheduled, 'expected' => $expected]), __('Gerenciar horário'), route('academic-years.classes.schedules.index', [$academicYear, $class]));
                }
            }
        }

        return $items;
    }

    /**
     * @return array{errors:int,warnings:int,info:int,total:int}
     */
    public static function summarize(array $items): array
    {
        $collection = collect($items);

        return [
            'errors' => $collection->where('level', 'danger')->count(),
            'warnings' => $collection->where('level', 'warning')->count(),
            'info' => $collection->where('level', 'info')->count(),
            'total' => $collection->count(),
        ];
    }

    /**
     * @return array{level:string,title:string,description:string,action_label?:string,action_url?:string}
     */
    private static function issue(string $level, string $title, string $description, ?string $actionLabel = null, ?string $actionUrl = null): array
    {
        return array_filter([
            'level' => $level,
            'title' => $title,
            'description' => $description,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
        ], fn ($value) => $value !== null);
    }

    private static function isBehaviorComponentName(?string $name): bool
    {
        return Str::lower(Str::ascii(trim((string) $name))) === 'comportamento';
    }
}
