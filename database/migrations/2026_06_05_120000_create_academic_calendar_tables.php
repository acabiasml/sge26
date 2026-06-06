<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('reference_year');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('approved_at')->nullable();
            $table->unsignedSmallInteger('class_hour_minutes')->default(50);
            $table->unsignedSmallInteger('minimum_school_days')->default(200);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'reference_year']);
        });

        Schema::create('academic_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedSmallInteger('position')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'starts_at', 'ends_at']);
        });

        Schema::create('calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->date('date');
            $table->string('type');
            $table->boolean('counts_as_school_day')->default(false);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'date']);
            $table->index(['academic_year_id', 'type']);
            $table->index(['academic_year_id', 'counts_as_school_day']);
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(true);
            $table->string('category')->default('evento');
            $table->boolean('highlight')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['academic_year_id']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('highlight')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'active', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_days');
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('academic_years');
    }
};
