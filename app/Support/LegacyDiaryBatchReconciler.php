<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyDiaryBatchReconciler
{
    public function reconcile(array $batch, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        $manifests = $batch['classes'] ?? [];
        if (($batch['format'] ?? '') !== 'legacy-diary-batch-v1' || ! is_array($manifests)
            || count($manifests) < 1 || count($manifests) > 20) {
            throw new RuntimeException('Lote de diários inválido.');
        }
        $classIds = array_column(array_column($manifests, 'target'), 'class_id');
        $courseIds = array_column(array_column($manifests, 'target'), 'course_id');
        $gradeRequests = $batch['technical_pdf_grade_corrections'] ?? [];
        if (! is_array($gradeRequests) || count($gradeRequests) > 20
            || count(array_unique(array_column($gradeRequests, 'result_id'))) !== count($gradeRequests)) {
            throw new RuntimeException('Correções técnicas duplicadas ou inválidas.');
        }
        $episodes = $batch['restore_enrollment_episodes'] ?? [];
        if (! is_array($episodes) || count($episodes) > 20
            || count(array_unique(array_column($episodes, 'current_enrollment_id'))) !== count($episodes)
            || collect($episodes)->contains(fn ($row) => ! in_array($row['class_id'] ?? 0, $classIds, true))) {
            throw new RuntimeException('Restauração de vínculos fora das turmas documentadas.');
        }
        $hourTargets = $batch['school_year_class_hours'] ?? [];
        if (! is_array($hourTargets) || count($hourTargets) > 10
            || count(array_unique(array_map(fn ($row) => ($row['school_id'] ?? 0).':'.($row['reference_year'] ?? 0), $hourTargets))) !== count($hourTargets)) {
            throw new RuntimeException('Anos duplicados ou inválidos para corrigir a duração.');
        }
        if (count(array_unique($classIds)) !== count($manifests) || count(array_unique($courseIds)) !== count($manifests)) {
            throw new RuntimeException('O lote precisa de turmas e matrizes distintas.');
        }
        $operation = function () use ($manifests, $classIds, $courseIds, $hourTargets, $episodes, $gradeRequests, $apply, $expectedSnapshot): array {
            if ($apply) {
                foreach ($hourTargets as $target) {
                    AcademicYear::where('school_id', $target['school_id'])->where('reference_year', $target['reference_year'])
                        ->orderBy('id')->lockForUpdate()->get();
                }
                SchoolClass::whereIn('id', $classIds)->orderBy('id')->lockForUpdate()->get();
                AcademicCourse::whereIn('id', $courseIds)->orderBy('id')->lockForUpdate()->get();
            }
            $service = app(LegacyClassDiaryReconciler::class);
            $hours = app(SchoolYearClassHourCorrection::class);
            $restorer = app(LegacyEnrollmentEpisodeRestorer::class);
            $gradeCorrector = app(TechnicalPdfGradeCorrection::class);
            $gradePlans = array_map(fn ($request) => $gradeCorrector->correct($request), $gradeRequests);
            $episodePlans = array_map(fn ($request) => $restorer->restore($request), $episodes);
            $hourPlans = array_map(fn ($target) => $hours->correct($target['school_id'], $target['reference_year'], $target['class_hour_minutes'],
                false, null, $target['regular_only'] ?? false, $target['original_import_minutes'] ?? null), $hourTargets);
            $plans = array_map(fn ($manifest) => $service->reconcile($manifest), $manifests);
            $hash = hash('sha256', json_encode([array_map(fn ($plan) => [
                $plan['class_id'], $plan['snapshot_sha256'], $plan['manifest_sha256'],
            ], $plans), $hourTargets, array_column($hourPlans, 'snapshot_sha256'), $episodes,
                array_column($episodePlans, 'snapshot_sha256'), $gradeRequests, array_column($gradePlans, 'snapshot_sha256')], JSON_THROW_ON_ERROR));
            if ($apply) {
                if (! $expectedSnapshot || ! hash_equals($hash, $expectedSnapshot)) {
                    throw new RuntimeException('O lote mudou após a conferência. Execute uma nova simulação.');
                }
                $hourPlans = array_map(fn ($target, $plan) => $hours->correct($target['school_id'], $target['reference_year'],
                    $target['class_hour_minutes'], true, $plan['snapshot_sha256'],
                    $target['regular_only'] ?? false, $target['original_import_minutes'] ?? null), $hourTargets, $hourPlans);
                $episodePlans = array_map(fn ($request, $plan) => $restorer->restore($request, true, $plan['snapshot_sha256']), $episodes, $episodePlans);
                $gradePlans = array_map(function ($request) use ($gradeCorrector) {
                    // A correção anual pode atualizar as horas de um histórico
                    // integrado; confere novamente esses campos já autorizados.
                    $current = $gradeCorrector->correct($request);

                    return $gradeCorrector->correct($request, true, $current['snapshot_sha256']);
                }, $gradeRequests);
                // O estado inicial de todo o lote já foi conferido. As mudanças
                // autorizadas de duração atualizam matriz e histórico antes das
                // chamadas; por isso cada turma recebe sua nova conferência aqui.
                $plans = array_map(function ($manifest) use ($service) {
                    $current = $service->reconcile($manifest);

                    return $service->reconcile($manifest, true, $current['snapshot_sha256']);
                }, $manifests);
            }
            $totals = [];
            foreach ($plans as $plan) {
                foreach ($plan['stats'] as $name => $value) {
                    $totals[$name] = ($totals[$name] ?? 0) + $value;
                }
            }

            return ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'stats' => $totals,
                'school_year_class_hours' => $hourPlans, 'restored_enrollment_episodes' => $episodePlans,
                'technical_pdf_grade_corrections' => $gradePlans, 'classes' => $plans];
        };

        return $apply ? DB::transaction($operation) : $operation();
    }
}
