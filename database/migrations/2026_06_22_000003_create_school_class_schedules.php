<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_class_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['school_class_id', 'starts_at', 'ends_at']);
        });

        Schema::create('school_class_schedule_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('type')->default('aula');
            $table->foreignId('school_class_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->index(['school_class_schedule_id', 'weekday', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_schedule_slots');
        Schema::dropIfExists('school_class_schedules');
    }
};
