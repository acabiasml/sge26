<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_period_diary_consolidations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('consolidated')->default(false);
            $table->timestamp('consolidated_at')->nullable();
            $table->foreignId('consolidated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_period_diary_consolidations');
    }
};
