<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('document_authorizations')->nullable();
        });
        Schema::table('people', function (Blueprint $table) {
            $table->string('school_voter_exemption', 150)->nullable();
        });
        Schema::table('academic_courses', function (Blueprint $table) {
            $table->string('technical_curriculum_regime', 12)->nullable();
        });
        Schema::table('diary_attendance_justifications', function (Blueprint $table) {
            $table->string('attendance_effect', 20)->nullable();
            $table->string('attendance_basis', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diary_attendance_justifications', fn (Blueprint $table) => $table->dropColumn(['attendance_effect', 'attendance_basis']));
        Schema::table('academic_courses', fn (Blueprint $table) => $table->dropColumn('technical_curriculum_regime'));
        Schema::table('people', fn (Blueprint $table) => $table->dropColumn('school_voter_exemption'));
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn('document_authorizations'));
    }
};
