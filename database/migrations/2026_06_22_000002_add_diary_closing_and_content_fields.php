<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->decimal('passing_score', 4, 1)->default(6)->after('minimum_school_days');
            $table->unsignedTinyInteger('minimum_attendance_percentage')->default(75)->after('passing_score');
        });

        Schema::table('school_assessment_rules', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('academic_period_id');
        });

        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->foreignId('updated_by_person_id')->nullable()->after('teacher_person_id')->constrained('people')->nullOnDelete();
        });

        Schema::table('diary_assessment_results', function (Blueprint $table): void {
            $table->foreignId('updated_by_person_id')->nullable()->after('student_enrollment_id')->constrained('people')->nullOnDelete();
        });

        Schema::create('diary_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->date('class_date');
            $table->text('content');
            $table->foreignId('created_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('updated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'curriculum_component_id', 'class_date'], 'diary_content_date_unique');
            $table->index(['academic_period_id', 'class_date']);
        });

        Schema::create('diary_period_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->boolean('confirmed')->default(false);
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_class_id', 'curriculum_component_id', 'academic_period_id'], 'diary_period_confirmation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_period_confirmations');
        Schema::dropIfExists('diary_contents');

        Schema::table('diary_assessment_results', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_person_id');
        });

        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_person_id');
        });

        Schema::table('school_assessment_rules', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropColumn(['passing_score', 'minimum_attendance_percentage']);
        });
    }
};
