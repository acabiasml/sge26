<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->dropUnique(['student_enrollment_id']);
            $table->unique(
                ['student_academic_history_id', 'student_enrollment_id'],
                'history_year_enrollment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->dropUnique('history_year_enrollment_unique');
            $table->unique('student_enrollment_id');
        });
    }
};
