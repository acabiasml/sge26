<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_course_technical_itinerary', function (Blueprint $table): void {
            $table->foreignId('academic_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technical_course_id')->constrained('academic_courses')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['academic_course_id', 'technical_course_id'], 'course_technical_itinerary_primary');
        });
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->foreignId('technical_itinerary_course_id')->nullable()->constrained('academic_courses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('technical_itinerary_course_id'));
        Schema::dropIfExists('academic_course_technical_itinerary');
    }
};
