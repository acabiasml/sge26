<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_class_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['school_class_id', 'curriculum_component_id'], 'class_component_unique');
            $table->index(['teacher_person_id', 'active']);
        });

        Schema::create('school_class_component_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_component_id')->constrained(indexName: 'class_sub_component_fk')->cascadeOnDelete();
            $table->foreignId('substitute_teacher_person_id')->constrained('people', indexName: 'class_sub_teacher_fk')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_class_component_id', 'starts_at', 'ends_at'], 'class_component_substitution_period_index');
            $table->index('substitute_teacher_person_id', 'class_component_substitute_teacher_index');
        });

        $now = now();

        DB::table('academic_course_school_class')
            ->join('curriculum_components', 'curriculum_components.academic_course_id', '=', 'academic_course_school_class.academic_course_id')
            ->where('curriculum_components.active', true)
            ->orderBy('academic_course_school_class.school_class_id')
            ->select([
                'academic_course_school_class.school_class_id',
                'curriculum_components.id as curriculum_component_id',
                'curriculum_components.teacher_person_id',
            ])
            ->chunk(500, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    DB::table('school_class_components')->updateOrInsert(
                        [
                            'school_class_id' => $row->school_class_id,
                            'curriculum_component_id' => $row->curriculum_component_id,
                        ],
                        [
                            'teacher_person_id' => $row->teacher_person_id,
                            'active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });

        DB::table('curriculum_component_substitutions')
            ->join('school_class_components', 'school_class_components.curriculum_component_id', '=', 'curriculum_component_substitutions.curriculum_component_id')
            ->orderBy('school_class_components.id')
            ->select([
                'school_class_components.id as school_class_component_id',
                'curriculum_component_substitutions.substitute_teacher_person_id',
                'curriculum_component_substitutions.starts_at',
                'curriculum_component_substitutions.ends_at',
                'curriculum_component_substitutions.notes',
            ])
            ->chunk(500, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    DB::table('school_class_component_substitutions')->insert([
                        'school_class_component_id' => $row->school_class_component_id,
                        'substitute_teacher_person_id' => $row->substitute_teacher_person_id,
                        'starts_at' => $row->starts_at,
                        'ends_at' => $row->ends_at,
                        'notes' => $row->notes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_component_substitutions');
        Schema::dropIfExists('school_class_components');
    }
};
