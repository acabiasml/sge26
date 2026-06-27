<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->decimal('maximum_score', 5, 2)->default(10);
            $table->date('assessment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_class_id', 'curriculum_component_id', 'academic_period_id'], 'diary_assessment_scope_index');
        });

        Schema::create('diary_assessment_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['diary_assessment_id', 'student_enrollment_id'], 'diary_assessment_result_unique');
        });

        Schema::create('diary_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->date('class_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_class_id', 'curriculum_component_id', 'class_date'], 'diary_attendance_record_unique');
        });

        Schema::create('diary_attendance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('presente');
            $table->timestamps();

            $table->unique(['diary_attendance_record_id', 'student_enrollment_id'], 'diary_attendance_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_attendance_entries');
        Schema::dropIfExists('diary_attendance_records');
        Schema::dropIfExists('diary_assessment_results');
        Schema::dropIfExists('diary_assessments');
    }
};
