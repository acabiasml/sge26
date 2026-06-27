<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_courses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('class_hour_minutes')
                ->default(50)
                ->after('workload_hours');
        });

        DB::table('academic_courses')
            ->select('academic_courses.id', 'academic_years.class_hour_minutes')
            ->join('academic_years', 'academic_courses.academic_year_id', '=', 'academic_years.id')
            ->orderBy('academic_courses.id')
            ->get()
            ->each(function (object $course): void {
                DB::table('academic_courses')
                    ->where('id', $course->id)
                    ->update(['class_hour_minutes' => $course->class_hour_minutes ?? 50]);
            });
    }

    public function down(): void
    {
        Schema::table('academic_courses', function (Blueprint $table): void {
            $table->dropColumn('class_hour_minutes');
        });
    }
};
