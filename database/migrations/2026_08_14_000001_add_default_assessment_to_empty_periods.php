<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_periods')
            || ! Schema::hasTable('academic_years')
            || ! Schema::hasTable('school_assessment_rules')) {
            return;
        }

        DB::table('academic_periods')
            ->join('academic_years', 'academic_years.id', '=', 'academic_periods.academic_year_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('school_assessment_rules')
                    ->whereColumn('school_assessment_rules.academic_period_id', 'academic_periods.id');
            })
            ->select(['academic_periods.id', 'academic_years.school_id'])
            ->orderBy('academic_periods.id')
            ->get()
            ->each(function (object $period): void {
                DB::table('school_assessment_rules')->insert([
                    'school_id' => $period->school_id,
                    'academic_period_id' => $period->id,
                    'name' => 'Avaliação 1',
                    'position' => 1,
                    'weight' => 10,
                    'maximum_score' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // A migração completa dados obrigatórios e não remove avaliações no rollback.
    }
};
