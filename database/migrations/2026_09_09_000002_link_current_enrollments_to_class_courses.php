<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $links = DB::table('student_enrollments as enrollments')
            ->join('school_classes as classes', 'classes.id', '=', 'enrollments.school_class_id')
            ->join('academic_years as years', 'years.id', '=', 'classes.academic_year_id')
            ->join('academic_course_school_class as class_courses', 'class_courses.school_class_id', '=', 'classes.id')
            ->leftJoin('academic_course_student_enrollment as enrollment_courses', function ($join): void {
                $join->on('enrollment_courses.student_enrollment_id', '=', 'enrollments.id')
                    ->on('enrollment_courses.academic_course_id', '=', 'class_courses.academic_course_id');
            })
            ->whereNull('enrollment_courses.student_enrollment_id')
            ->where('enrollments.status', 'matriculado')
            ->where('classes.active', true)
            ->where('years.active', true)
            ->whereNull('years.closed_at')
            ->where(fn ($query) => $query->whereNull('enrollments.final_result_status')
                ->orWhere('enrollments.final_result_status', 'pendente'))
            ->select(['enrollments.id as student_enrollment_id', 'class_courses.academic_course_id'])
            ->get();

        foreach ($links->chunk(500) as $chunk) {
            DB::table('academic_course_student_enrollment')->insertOrIgnore(
                $chunk->map(fn ($link): array => [
                    'student_enrollment_id' => $link->student_enrollment_id,
                    'academic_course_id' => $link->academic_course_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }
    }

    public function down(): void
    {
        // Keep academic links: subsequent grades may already depend on them.
    }
};
