<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship_type');
            $table->string('cpf', 20)->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('legal_guardian')->default(false);
            $table->boolean('emergency_contact')->default(false);
            $table->text('notes')->nullable();
            $table->string('legacy_source')->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'relationship_type']);
            $table->index(['legacy_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_contacts');
    }
};
