<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('knowledge_areas')->insert([
            ['name' => 'Linguagens', 'sort_order' => 10, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Linguagens e suas Tecnologias', 'sort_order' => 20, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Matemática', 'sort_order' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Matemática e suas Tecnologias', 'sort_order' => 40, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ciências da Natureza', 'sort_order' => 50, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ciências da Natureza e suas Tecnologias', 'sort_order' => 60, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ciências Humanas', 'sort_order' => 70, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ciências Humanas e Sociais Aplicadas', 'sort_order' => 80, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ensino Religioso', 'sort_order' => 90, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Parte Diversificada', 'sort_order' => 100, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Itinerário Formativo', 'sort_order' => 110, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Educação Profissional e Tecnológica', 'sort_order' => 120, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('academic_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('starts_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->foreignId('ends_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('name');
            $table->string('stage')->default('outro');
            $table->string('modality')->nullable();
            $table->string('status')->default('planejado');
            $table->unsignedInteger('workload_hours')->nullable();
            $table->text('notes')->nullable();
            $table->string('legacy_source')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::create('curriculum_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('name');
            $table->decimal('weekly_lessons', 5, 2)->nullable();
            $table->unsignedInteger('annual_lessons')->nullable();
            $table->unsignedInteger('workload_hours')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->string('legacy_source')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::create('school_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('shift')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        Schema::create('academic_course_school_class', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['academic_course_id', 'school_class_id'], 'course_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_course_school_class');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('curriculum_components');
        Schema::dropIfExists('academic_courses');
        Schema::dropIfExists('knowledge_areas');
    }
};
