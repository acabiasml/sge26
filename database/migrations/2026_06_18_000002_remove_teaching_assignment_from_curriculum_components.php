<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('curriculum_component_substitutions');

        if (Schema::hasColumn('curriculum_components', 'teacher_person_id')) {
            Schema::table('curriculum_components', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('teacher_person_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('curriculum_components', 'teacher_person_id')) {
            Schema::table('curriculum_components', function (Blueprint $table): void {
                $table->foreignId('teacher_person_id')->nullable()->after('knowledge_area_id')->constrained('people')->nullOnDelete();
            });
        }

        Schema::create('curriculum_component_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substitute_teacher_person_id')->constrained('people')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['curriculum_component_id', 'starts_at', 'ends_at'], 'component_substitution_period_index');
        });
    }
};
