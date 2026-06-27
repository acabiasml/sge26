<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('enrolled_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('transferred_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->date('enrolled_at')->nullable();
            $table->date('transferred_at')->nullable();
            $table->string('status')->default('matriculado');
            $table->string('type')->default('regular');
            $table->text('notes')->nullable();
            $table->string('legacy_source')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->timestamps();

            $table->unique(['school_class_id', 'person_id']);
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::create('academic_course_student_enrollment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['academic_course_id', 'student_enrollment_id'], 'course_enrollment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_course_student_enrollment');
        Schema::dropIfExists('student_enrollments');
    }
};
