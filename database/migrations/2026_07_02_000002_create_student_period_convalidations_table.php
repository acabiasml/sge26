<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_period_convalidations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained('curriculum_components')->cascadeOnDelete();
            $table->foreignId('convalidated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->decimal('score', 4, 1);
            $table->string('source_school')->nullable();
            $table->text('notes')->nullable();
            $table->date('convalidated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'academic_period_id', 'curriculum_component_id'], 'student_period_component_convalidation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_period_convalidations');
    }
};
