<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history_curriculum_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('history_curriculum_component_id')->constrained(indexName: 'history_alias_target_fk')->cascadeOnDelete();
            $table->foreignId('knowledge_area_id')->constrained(indexName: 'history_alias_area_fk')->restrictOnDelete();
            $table->string('education_stage', 20);
            $table->string('formation', 255);
            $table->string('name');
            $table->timestamps();
            $table->unique(['education_stage', 'formation', 'knowledge_area_id', 'name'], 'history_alias_context_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_curriculum_aliases');
    }
};
