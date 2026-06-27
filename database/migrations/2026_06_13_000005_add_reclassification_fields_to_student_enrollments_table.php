<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->foreignId('reclassified_from_enrollment_id')
                ->nullable()
                ->after('transferred_by_person_id')
                ->constrained('student_enrollments')
                ->nullOnDelete();
            $table->foreignId('reclassified_by_person_id')
                ->nullable()
                ->after('reclassified_from_enrollment_id')
                ->constrained('people')
                ->nullOnDelete();
            $table->date('reclassified_at')->nullable()->after('transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reclassified_by_person_id');
            $table->dropConstrainedForeignId('reclassified_from_enrollment_id');
            $table->dropColumn('reclassified_at');
        });
    }
};
