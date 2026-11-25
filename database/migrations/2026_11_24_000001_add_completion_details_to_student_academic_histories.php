<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->date('course_entered_on')->nullable();
            $table->string('course_entry_method', 120)->nullable();
            $table->unsignedSmallInteger('course_entry_year')->nullable();
            $table->unsignedTinyInteger('course_entry_semester')->nullable();
            $table->date('course_completed_on')->nullable();
            $table->text('course_complementary_activities')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->dropColumn(['course_entered_on', 'course_entry_method', 'course_entry_year', 'course_entry_semester', 'course_completed_on', 'course_complementary_activities']);
        });
    }
};
