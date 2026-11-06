<?php

use App\Support\HistoryCurriculumPropagation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $fields = ['id', 'student_academic_history_year_id', 'score_label', 'score_numeric', 'workload_hours', 'completed_workload_hours', 'frequency_label', 'frequency_percentage', 'absences', 'result', 'created_at', 'updated_at'];
            $before = DB::table('student_academic_history_records')->orderBy('id')->lockForUpdate()->get($fields)->toJson();
            $propagation = app(HistoryCurriculumPropagation::class);
            $propagation->repairFromAudits();
            $propagation->backfillMatrixOrigins();
            $after = DB::table('student_academic_history_records')->orderBy('id')->get($fields)->toJson();
            if ($before !== $after) {
                throw new RuntimeException('A propagação deve preservar integralmente os resultados e os anos dos registros históricos.');
            }
        });
    }

    public function down(): void
    {
        // Classificações corrigidas por alterações auditadas não são revertidas.
    }
};
