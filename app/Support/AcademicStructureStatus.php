<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Carbon\CarbonInterface;

class AcademicStructureStatus
{
    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function academicYear(AcademicYear $academicYear, ?CarbonInterface $today = null): array
    {
        $today ??= now('America/Sao_Paulo');

        if (! $academicYear->active) {
            return ['label' => __('Inativo'), 'tone' => 'secondary', 'description' => __('Ano letivo oculto das rotinas de uso.')];
        }

        if ($academicYear->ends_at && $academicYear->ends_at->lt($today->startOfDay())) {
            return ['label' => __('Encerrado'), 'tone' => 'secondary', 'description' => __('Período letivo encerrado pela data final.')];
        }

        if ($academicYear->starts_at && $academicYear->starts_at->lte($today) && $academicYear->ends_at && $academicYear->ends_at->gte($today)) {
            return ['label' => $academicYear->approved_at ? __('Em andamento') : __('Em andamento sem aprovação'), 'tone' => $academicYear->approved_at ? 'success' : 'warning', 'description' => __('Ano letivo dentro do período de execução.')];
        }

        return ['label' => $academicYear->approved_at ? __('Aprovado') : __('Planejado'), 'tone' => $academicYear->approved_at ? 'info' : 'warning', 'description' => __('Ano letivo ainda não iniciado.')];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function course(AcademicCourse $course): array
    {
        if (! $course->hasMatrixComponents()) {
            return ['label' => __('Incompleta'), 'tone' => 'danger', 'description' => __('Ainda não possui componentes curriculares.')];
        }

        if ($course->relationLoaded('classes') && $course->classes->isEmpty()) {
            return ['label' => __('Sem turma'), 'tone' => 'info', 'description' => __('Matriz cadastrada, ainda não vinculada a uma turma.')];
        }

        return ['label' => __('Organizada'), 'tone' => 'success', 'description' => __('Matriz com estrutura curricular básica.')];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function schoolClass(SchoolClass $class): array
    {
        if (! $class->active) {
            return ['label' => __('Inativa'), 'tone' => 'secondary', 'description' => __('Turma fora de uso.')];
        }

        $missingTeachers = $class->componentAssignments
            ->where('active', true)
            ->filter(fn ($assignment): bool => blank($assignment->teacher_person_id))
            ->isNotEmpty();

        if ($class->courses->isEmpty() || $class->componentAssignments->isEmpty()) {
            return ['label' => __('Incompleta'), 'tone' => 'danger', 'description' => __('Turma sem matriz ou sem componentes vinculados.')];
        }

        if ($missingTeachers) {
            return ['label' => __('Em preparação'), 'tone' => 'warning', 'description' => __('Ainda há componentes sem docência titular.')];
        }

        return ['label' => __('Pronta'), 'tone' => 'success', 'description' => __('Turma com estrutura básica validada.')];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function period(AcademicPeriod $period): array
    {
        $period->loadMissing('diaryConsolidation');

        if ($period->diaryConsolidation?->consolidated) {
            return ['label' => __('Consolidado'), 'tone' => 'success', 'description' => __('Diários fechados pela gestão.')];
        }

        if ($period->ends_at && $period->ends_at->lt(now('America/Sao_Paulo')->startOfDay())) {
            return ['label' => __('Aguardando fechamento'), 'tone' => 'warning', 'description' => __('Período encerrado, mas ainda não consolidado.')];
        }

        return ['label' => __('Aberto'), 'tone' => 'info', 'description' => __('Período disponível para lançamentos.')];
    }
}
