<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_period_convalidations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attendance_lessons')->nullable()->after('score');
            $table->unsignedSmallInteger('attendance_absences')->nullable()->after('attendance_lessons');
            $table->unsignedSmallInteger('attendance_justified_absences')->nullable()->after('attendance_absences');
        });
    }

    public function down(): void
    {
        Schema::table('student_period_convalidations', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_lessons',
                'attendance_absences',
                'attendance_justified_absences',
            ]);
        });
    }
};
