<?php

use App\Models\StudentEnrollment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_enrollments')
            ->where('status', StudentEnrollment::STATUS_RECLASSIFIED)
            ->where(function ($query): void {
                $query->whereNull('final_result_status')
                    ->orWhere('final_result_status', StudentEnrollment::FINAL_PENDING);
            })
            ->update([
                'final_result_status' => StudentEnrollment::FINAL_RECLASSIFIED,
                'final_result_details' => json_encode(['reason' => 'Matrícula encerrada por reclassificação.'], JSON_THROW_ON_ERROR),
                'final_result_calculated_at' => now(),
                'final_result_calculated_by_person_id' => null,
            ]);
    }

    public function down(): void
    {
        // Não é seguro remover resultados definitivos após a atualização histórica.
    }
};
