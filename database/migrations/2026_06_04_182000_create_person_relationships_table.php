<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('related_person_id')->constrained('people')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->boolean('legal_guardian')->default(false);
            $table->boolean('emergency_contact')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'related_person_id', 'relationship_type'], 'person_rel_unique');
            $table->index(['related_person_id', 'relationship_type'], 'person_rel_related_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_relationships');
    }
};
