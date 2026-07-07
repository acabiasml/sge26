<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('created_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('updated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('title')->default('Histórico escolar');
            $table->string('stage')->nullable();
            $table->text('legal_basis')->nullable();
            $table->text('notes')->nullable();
            $table->string('issued_place')->nullable();
            $table->date('issued_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_academic_history_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_academic_history_id')->constrained('student_academic_histories', indexName: 'history_year_history_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('label');
            $table->string('year')->nullable();
            $table->string('stage')->nullable();
            $table->string('modality')->nullable();
            $table->string('grade_phase')->nullable();
            $table->string('school_name')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('final_result')->nullable();
            $table->decimal('workload_hours', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('student_academic_history_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_academic_history_id')->constrained('student_academic_histories', indexName: 'history_comp_history_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('formation')->nullable();
            $table->string('knowledge_area')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('student_academic_history_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_academic_history_component_id')->constrained('student_academic_history_components', indexName: 'history_rec_component_fk')->cascadeOnDelete();
            $table->foreignId('student_academic_history_year_id')->constrained('student_academic_history_years', indexName: 'history_rec_year_fk')->cascadeOnDelete();
            $table->string('score_label')->nullable();
            $table->decimal('score_numeric', 5, 2)->nullable();
            $table->decimal('workload_hours', 8, 2)->nullable();
            $table->string('frequency_label')->nullable();
            $table->decimal('frequency_percentage', 5, 2)->nullable();
            $table->string('result')->nullable();
            $table->timestamps();

            $table->unique(['student_academic_history_component_id', 'student_academic_history_year_id'], 'history_component_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_history_records');
        Schema::dropIfExists('student_academic_history_components');
        Schema::dropIfExists('student_academic_history_years');
        Schema::dropIfExists('student_academic_histories');
    }
};
