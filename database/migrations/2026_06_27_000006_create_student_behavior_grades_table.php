<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_behavior_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_period_id', 'student_enrollment_id'], 'student_behavior_grade_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_behavior_grades');
    }
};
