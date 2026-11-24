<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->foreignId('reclassification_registration_id')->nullable()->constrained('credential_registrations')->restrictOnDelete();
            $table->unique('reclassification_registration_id', 'enrollment_reclassification_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollment_reclassification_registration_unique');
            $table->dropConstrainedForeignId('reclassification_registration_id');
        });
    }
};
