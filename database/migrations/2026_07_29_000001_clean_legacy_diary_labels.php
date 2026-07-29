<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('diary_attendance_records')
            ->where('notes', 'like', 'Chamada importada da base legada%')
            ->update(['notes' => null]);

        DB::table('diary_assessments')
            ->where('title', 'Média legada')
            ->update(['title' => 'Média do período']);

        DB::table('diary_assessments')
            ->where('notes', 'like', 'Média importada da base legada%')
            ->update(['notes' => null]);

        DB::table('diary_assessment_results')
            ->where('notes', 'like', 'Registro legado #%')
            ->update(['notes' => null]);
    }

    public function down(): void
    {
        //
    }
};
