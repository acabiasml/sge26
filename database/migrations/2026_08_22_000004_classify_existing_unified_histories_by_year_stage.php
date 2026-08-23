<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $historyIds = DB::table('student_academic_histories as histories')
            ->where('histories.is_unified', true)
            ->where('histories.education_stage', 'fundamental')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('student_academic_history_years as years')
                    ->whereColumn('years.student_academic_history_id', 'histories.id')
                    ->where('years.stage', 'like', '%Médio%');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('student_academic_history_years as years')
                    ->whereColumn('years.student_academic_history_id', 'histories.id')
                    ->where('years.stage', 'like', '%Fundamental%');
            })
            ->pluck('histories.id');

        DB::table('student_academic_histories')->whereIn('id', $historyIds)->update([
            'education_stage' => 'medio',
            'stage' => 'Ensino Médio',
            'title' => 'Histórico escolar - Ensino Médio',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // A classificação deriva dos próprios anos e não deve ser revertida.
    }
};
