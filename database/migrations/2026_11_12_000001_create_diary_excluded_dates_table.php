<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_excluded_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->date('class_date');
            $table->timestamps();
            $table->unique(['school_class_component_id', 'academic_period_id', 'class_date'], 'diary_excluded_dates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_excluded_dates');
    }
};
