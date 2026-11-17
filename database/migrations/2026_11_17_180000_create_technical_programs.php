<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('graduate_title');
            foreach (['technological_axis', 'education_mode', 'offer_forms', 'maintainer', 'state_heading', 'education_system'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['creation_act', 'accreditation_act', 'authorization_act', 'legal_basis', 'registry_resolution', 'front_address', 'back_address', 'regulatory_process', 'regulatory_opinion', 'official_gazette_reference', 'source_project'] as $field) {
                $table->text($field)->nullable();
            }
            $table->date('authorization_starts_at')->nullable();
            $table->date('authorization_ends_at')->nullable();
            $table->json('modules');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });
        Schema::table('academic_courses', fn (Blueprint $table) => $table->foreignId('technical_program_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('curriculum_components', function (Blueprint $table): void {
            $table->string('technical_program_component_key', 80)->nullable()->index();
        });
        Schema::table('academic_course_student_enrollment', fn (Blueprint $table) => $table->string('technical_offer_form', 20)->nullable());
        Schema::table('issued_documents', fn (Blueprint $table) => $table->string('registration_key', 64)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('academic_course_student_enrollment', fn (Blueprint $table) => $table->dropColumn('technical_offer_form'));
        Schema::table('issued_documents', function (Blueprint $table): void {
            $table->dropUnique(['registration_key']);
            $table->dropColumn('registration_key');
        });
        Schema::table('curriculum_components', fn (Blueprint $table) => $table->dropColumn('technical_program_component_key'));
        Schema::table('academic_courses', fn (Blueprint $table) => $table->dropConstrainedForeignId('technical_program_id'));
        Schema::dropIfExists('technical_programs');
    }
};
