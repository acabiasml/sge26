<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_history_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('student_academic_history_id')->nullable()
                ->constrained(indexName: 'history_draft_history_fk')->cascadeOnDelete();
            // Zero identifica o formulário de inclusão de anos externos.
            $table->unsignedBigInteger('editor_key');
            $table->uuid('revision');
            $table->string('history_version', 64)->nullable();
            $table->longText('payload');
            $table->timestamps();
            $table->unique(['user_id', 'person_id', 'editor_key'], 'history_draft_editor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_history_drafts');
    }
};
