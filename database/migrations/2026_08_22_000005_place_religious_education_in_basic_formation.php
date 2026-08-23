<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_academic_history_components')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['ensino religioso'])
            ->update(['formation' => 'Formação Geral Básica', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // A formação correta não deve ser revertida.
    }
};
