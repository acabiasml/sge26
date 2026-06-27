<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_components', function (Blueprint $table): void {
            $table->foreignId('starts_period_id')->nullable()->after('knowledge_area_id')->constrained('academic_periods')->nullOnDelete();
            $table->foreignId('ends_period_id')->nullable()->after('starts_period_id')->constrained('academic_periods')->nullOnDelete();
        });

        Schema::create('diary_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('to_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_alerts');

        Schema::table('curriculum_components', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ends_period_id');
            $table->dropConstrainedForeignId('starts_period_id');
        });
    }
};
