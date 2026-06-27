<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolClassScheduleSlot;

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
            'classes.componentAssignments.teacher',
            'classes.enrollments',
        ]);

        $items = [];
        $schoolDays = $academicYear->days->where('counts_as_school_day', true)->count();

        if ($academicYear->periods->isEmpty()) {
            $items[] = self::issue('danger', 'Nenhum período avaliativo cadastrado', 'Cadastre os períodos para permitir avaliações, diários e fechamento por etapa.', 'Gerenciar períodos', route('academic-years.periods.index', $academicYear));
        }

        if ($schoolDays < (int) $academicYear->minimum_school_days) {
            $items[] = self::issue('warning', 'Dias letivos abaixo do mínimo', "O calendário possui {$schoolDays} dias letivos. O mínimo configurado é {$academicYear->minimum_school_days}.", 'Abrir calendário', route('academic-years.show', $academicYear).'#section-calendario');
        }

        if ($academicYear->courses->isEmpty()) {
            $items[] = self::issue('danger', 'Nenhuma matriz cadastrada', 'Crie ao menos uma matriz curricular antes de montar turmas.', 'Nova matriz', route('academic-years.courses.create', $academicYear));
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

        if (! $course->active) {
            $items[] = self::issue('warning', 'Matriz inativa', 'Matrizes inativas não devem receber novas turmas ou matrículas.', 'Editar matriz', route('academic-years.courses.edit', [$course->academic_year_id, $course]));
        }

        if ($course->components->where('active', true)->isEmpty()) {
            $items[] = self::issue('danger', 'Matriz sem componentes ativos', 'Inclua os componentes curriculares antes de criar turmas para esta matriz.', 'Gerenciar matriz', $baseUrl);
        }

        foreach ($course->components->where('active', true) as $component) {
            if (blank($component->knowledge_area_id)) {
                $items[] = self::issue('warning', 'Componente sem área', "{$component->name} ainda não possui área do conhecimento.", 'Abrir componente', route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }

            if ((int) $component->weekly_lessons < 1) {
                $items[] = self::issue('warning', 'Componente sem aulas semanais', "{$component->name} precisa de aulas semanais para cálculo de carga horária e horários.", 'Abrir componente', route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }

            $starts = $component->startsPeriod ?? $course->startsPeriod;
            $ends = $component->endsPeriod ?? $course->endsPeriod;

            if ($starts && $ends && $starts->position > $ends->position) {
                $items[] = self::issue('danger', 'Duração inválida de componente', "{$component->name} começa depois do período final informado.", 'Abrir componente', route('academic-years.courses.components.show', [$course->academic_year_id, $course, $component]));
            }
        }

        if ($course->components->where('active', true)->isNotEmpty() && $course->classes->isEmpty()) {
            $items[] = self::issue('info', 'Matriz ainda sem turma', 'A matriz está cadastrada, mas ainda não foi vinculada a nenhuma turma.', 'Criar turma', route('academic-years.classes.create', $course->academic_year_id));
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
            $items[] = self::issue('warning', 'Turma inativa', 'Turmas inativas não devem receber matrículas nem lançamentos de diário.', 'Editar turma', route('academic-years.classes.edit', [$academicYear, $class]));
        }

        if ($class->courses->isEmpty()) {
            $items[] = self::issue('danger', 'Turma sem matriz', 'Vincule ao menos uma matriz ativa para gerar componentes, matrículas e diários.', 'Editar turma', route('academic-years.classes.edit', [$academicYear, $class]));
        }

        if ($class->enrollments->isEmpty()) {
            $items[] = self::issue('info', 'Turma sem matrículas', 'Nenhum estudante foi matriculado nesta turma ainda.', 'Gerenciar matrículas', route('classes.enrollments.index', $class));
        }

        foreach ($class->componentAssignments->where('active', true) as $assignment) {
            if (blank($assignment->teacher_person_id)) {
                $items[] = self::issue('warning', 'Componente sem docência titular', ($assignment->component?->name ?? 'Componente sem nome').' ainda não possui professor titular.', 'Gerenciar turma', route('academic-years.classes.show', [$academicYear, $class]));
            }
        }

        $scheduledCounts = $class->schedules
            ->flatMap(fn ($schedule) => $schedule->slots)
            ->filter(fn ($slot) => $slot->type === SchoolClassScheduleSlot::TYPE_CLASS && $slot->school_class_component_id)
            ->groupBy('school_class_component_id')
            ->map->count();

        foreach ($class->componentAssignments->where('active', true) as $assignment) {
            $expected = (int) ($assignment->component?->weekly_lessons ?? 0);

            if ($expected > 0 && $class->schedules->isNotEmpty()) {
                $scheduled = (int) ($scheduledCounts[$assignment->id] ?? 0);

                if ($scheduled === 0) {
                    $items[] = self::issue('warning', 'Componente fora do horário', ($assignment->component?->name ?? 'Componente sem nome').' ainda não aparece em nenhum bloco de horário.', 'Gerenciar horário', route('academic-years.classes.schedules.index', [$academicYear, $class]));
                } elseif ($scheduled > $expected) {
                    $items[] = self::issue('danger', 'Horário acima das aulas semanais', ($assignment->component?->name ?? 'Componente sem nome')." possui {$scheduled} bloco(s), mas a matriz prevê {$expected}.", 'Gerenciar horário', route('academic-years.classes.schedules.index', [$academicYear, $class]));
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
}
