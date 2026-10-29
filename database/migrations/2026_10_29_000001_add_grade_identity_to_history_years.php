<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->unsignedTinyInteger('grade_number')->nullable()->after('grade_phase');
            $table->unsignedBigInteger('imported_from_history_year_id')->nullable();
            $table->unique(['student_academic_history_id', 'imported_from_history_year_id'], 'history_import_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->dropUnique('history_import_source_unique');
            $table->dropColumn(['grade_number', 'imported_from_history_year_id']);
        });
    }
};
