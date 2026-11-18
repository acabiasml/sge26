<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('workload_hours', 8, 2);
            $table->text('syllabus');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });
        Schema::create('free_course_offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('free_course_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('delivery_mode', 20)->default('presencial');
            $table->string('location');
            $table->timestamps();
        });
        Schema::create('free_course_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('free_course_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->date('completed_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['free_course_offering_id', 'person_id'], 'free_course_participant_unique');
        });
        Schema::table('issued_documents', function (Blueprint $table): void {
            $table->uuid('batch_uuid')->nullable()->index();
            $table->string('file_sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('issued_documents', fn (Blueprint $table) => $table->dropColumn(['batch_uuid', 'file_sha256']));
        Schema::dropIfExists('free_course_participants');
        Schema::dropIfExists('free_course_offerings');
        Schema::dropIfExists('free_courses');
    }
};
