<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->string('country')->nullable()->after('state');
            $table->string('transcript_mode')->default('detailed')->after('country');
            $table->unsignedSmallInteger('school_days')->nullable()->after('workload_hours');
            $table->string('attendance_label')->nullable()->after('school_days');
            $table->decimal('minimum_attendance_percentage', 5, 2)->nullable()->after('attendance_label');
            $table->text('notes')->nullable()->after('minimum_attendance_percentage');
        });

        Schema::table('student_academic_history_records', function (Blueprint $table): void {
            $table->unsignedSmallInteger('absences')->nullable()->after('frequency_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_records', function (Blueprint $table): void {
            $table->dropColumn('absences');
        });

        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->dropColumn([
                'country',
                'transcript_mode',
                'school_days',
                'attendance_label',
                'minimum_attendance_percentage',
                'notes',
            ]);
        });
    }
};
