<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->boolean('is_unified')->default(false)->after('active')->index();
        });

        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->string('source', 30)->default('manual')->after('position');
            $table->foreignId('student_enrollment_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->text('school_authorization')->nullable()->after('school_name');
            $table->string('source_document')->nullable()->after('school_authorization');
            $table->unique('student_enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_years', function (Blueprint $table): void {
            $table->dropUnique(['student_enrollment_id']);
            $table->dropConstrainedForeignId('student_enrollment_id');
            $table->dropColumn(['source', 'school_authorization', 'source_document']);
        });

        Schema::table('student_academic_histories', function (Blueprint $table): void {
            $table->dropColumn('is_unified');
        });
    }
};
