<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_assessment_rules')) {
            DB::table('school_assessment_rules')
                ->where('name', 'Média do período')
                ->where('weight', 1)
                ->update([
                    'weight' => 10,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('diary_assessments')) {
            DB::table('diary_assessments')
                ->where('title', 'Média do período')
                ->where('weight', 1)
                ->update([
                    'weight' => 10,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // A normalização corrige o padrão funcional e não deve ser revertida.
    }
};
