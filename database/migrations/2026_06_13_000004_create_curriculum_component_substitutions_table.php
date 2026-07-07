<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_component_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_component_id')->constrained(indexName: 'cc_sub_component_fk')->cascadeOnDelete();
            $table->foreignId('substitute_teacher_person_id')->constrained('people', indexName: 'cc_sub_teacher_fk')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['curriculum_component_id', 'starts_at', 'ends_at'], 'component_substitution_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_component_substitutions');
    }
};
