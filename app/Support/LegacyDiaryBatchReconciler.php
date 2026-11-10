<?php

namespace App\Support;

use App\Models\AcademicCourse;
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
        if (count(array_unique($classIds)) !== count($manifests) || count(array_unique($courseIds)) !== count($manifests)) {
            throw new RuntimeException('O lote precisa de turmas e matrizes distintas.');
        }
        $operation = function () use ($manifests, $classIds, $courseIds, $apply, $expectedSnapshot): array {
            if ($apply) {
                SchoolClass::whereIn('id', $classIds)->orderBy('id')->lockForUpdate()->get();
                AcademicCourse::whereIn('id', $courseIds)->orderBy('id')->lockForUpdate()->get();
            }
            $service = app(LegacyClassDiaryReconciler::class);
            $plans = array_map(fn ($manifest) => $service->reconcile($manifest), $manifests);
            $hash = hash('sha256', json_encode(array_map(fn ($plan) => [
                $plan['class_id'], $plan['snapshot_sha256'], $plan['manifest_sha256'],
            ], $plans), JSON_THROW_ON_ERROR));
            if ($apply) {
                if (! $expectedSnapshot || ! hash_equals($hash, $expectedSnapshot)) {
                    throw new RuntimeException('O lote mudou após a conferência. Execute uma nova simulação.');
                }
                $plans = array_map(fn ($manifest, $plan) => $service->reconcile($manifest, true, $plan['snapshot_sha256']), $manifests, $plans);
            }
            $totals = [];
            foreach ($plans as $plan) {
                foreach ($plan['stats'] as $name => $value) {
                    $totals[$name] = ($totals[$name] ?? 0) + $value;
                }
            }

            return ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'stats' => $totals, 'classes' => $plans];
        };

        return $apply ? DB::transaction($operation) : $operation();
    }
}
