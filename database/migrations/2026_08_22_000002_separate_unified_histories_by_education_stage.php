<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->string('education_stage', 30)->nullable()->after('is_unified')->index();
        });

        DB::table('student_academic_histories')
            ->where('is_unified', true)
            ->whereNull('education_stage')
            ->update(['education_stage' => 'fundamental']);

        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->unique(['person_id', 'is_unified', 'education_stage'], 'student_history_unified_stage_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->dropUnique('student_history_unified_stage_unique');
            $table->dropColumn('education_stage');
        });
    }
};
