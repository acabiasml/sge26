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
            return ['label' => 'Inativo', 'tone' => 'secondary', 'description' => 'Ano letivo oculto das rotinas de uso.'];
        }

        if ($academicYear->ends_at && $academicYear->ends_at->lt($today->startOfDay())) {
            return ['label' => 'Encerrado', 'tone' => 'secondary', 'description' => 'Período letivo encerrado pela data final.'];
        }

        if ($academicYear->starts_at && $academicYear->starts_at->lte($today) && $academicYear->ends_at && $academicYear->ends_at->gte($today)) {
            return ['label' => $academicYear->approved_at ? 'Em andamento' : 'Em andamento sem aprovação', 'tone' => $academicYear->approved_at ? 'success' : 'warning', 'description' => 'Ano letivo dentro do período de execução.'];
        }

        return ['label' => $academicYear->approved_at ? 'Aprovado' : 'Planejado', 'tone' => $academicYear->approved_at ? 'info' : 'warning', 'description' => 'Ano letivo ainda não iniciado.'];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function course(AcademicCourse $course): array
    {
        if (! $course->hasMatrixComponents()) {
            return ['label' => 'Incompleta', 'tone' => 'danger', 'description' => 'Ainda não possui componentes curriculares.'];
        }

        if ($course->relationLoaded('classes') && $course->classes->isEmpty()) {
            return ['label' => 'Sem turma', 'tone' => 'info', 'description' => 'Matriz cadastrada, ainda não vinculada a uma turma.'];
        }

        return ['label' => 'Organizada', 'tone' => 'success', 'description' => 'Matriz com estrutura curricular básica.'];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function schoolClass(SchoolClass $class): array
    {
        if (! $class->active) {
            return ['label' => 'Inativa', 'tone' => 'secondary', 'description' => 'Turma fora de uso.'];
        }

        $missingTeachers = $class->componentAssignments
            ->where('active', true)
            ->filter(fn ($assignment): bool => blank($assignment->teacher_person_id))
            ->isNotEmpty();

        if ($class->courses->isEmpty() || $class->componentAssignments->isEmpty()) {
            return ['label' => 'Incompleta', 'tone' => 'danger', 'description' => 'Turma sem matriz ou sem componentes vinculados.'];
        }

        if ($missingTeachers) {
            return ['label' => 'Em preparação', 'tone' => 'warning', 'description' => 'Ainda há componentes sem docência titular.'];
        }

        return ['label' => 'Pronta', 'tone' => 'success', 'description' => 'Turma com estrutura básica validada.'];
    }

    /**
     * @return array{label:string,tone:string,description:string}
     */
    public static function period(AcademicPeriod $period): array
    {
        $period->loadMissing('diaryConsolidation');

        if ($period->diaryConsolidation?->consolidated) {
            return ['label' => 'Consolidado', 'tone' => 'success', 'description' => 'Diários fechados pela gestão.'];
        }

        if ($period->ends_at && $period->ends_at->lt(now('America/Sao_Paulo')->startOfDay())) {
            return ['label' => 'Aguardando fechamento', 'tone' => 'warning', 'description' => 'Período encerrado, mas ainda não consolidado.'];
        }

        return ['label' => 'Aberto', 'tone' => 'info', 'description' => 'Período disponível para lançamentos.'];
    }
}
