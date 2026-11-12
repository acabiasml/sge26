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
            $table->foreignId('technical_course_id')->nullable()->constrained('academic_courses')->restrictOnDelete();
            $table->unsignedBigInteger('technical_course_scope')->virtualAs('coalesce(technical_course_id, 0)');
            $table->unique(['person_id', 'is_unified', 'education_stage', 'technical_course_scope'], 'student_history_stage_course_unique');
            $table->dropUnique('student_history_unified_stage_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('student_academic_histories')->select('person_id', 'is_unified', 'education_stage')
            ->whereNotNull('education_stage')->groupBy('person_id', 'is_unified', 'education_stage')
            ->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Há históricos separados por curso. O rollback não pode reuni-los nem descartar dados.');
        }
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->unique(['person_id', 'is_unified', 'education_stage'], 'student_history_unified_stage_unique');
            $table->dropUnique('student_history_stage_course_unique');
            $table->dropColumn('technical_course_scope');
            $table->dropConstrainedForeignId('technical_course_id');
        });
    }
};
