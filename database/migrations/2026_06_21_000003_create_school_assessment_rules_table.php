<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_assessment_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->decimal('maximum_score', 5, 2)->default(10);
            $table->timestamps();

            $table->unique(['school_id', 'academic_period_id', 'position'], 'school_assessment_rule_position_unique');
        });

        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->foreignId('school_assessment_rule_id')
                ->nullable()
                ->after('academic_period_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique([
                'school_class_id',
                'curriculum_component_id',
                'academic_period_id',
                'school_assessment_rule_id',
            ], 'diary_assessment_rule_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->dropUnique('diary_assessment_rule_scope_unique');
            $table->dropConstrainedForeignId('school_assessment_rule_id');
        });

        Schema::dropIfExists('school_assessment_rules');
    }
};
