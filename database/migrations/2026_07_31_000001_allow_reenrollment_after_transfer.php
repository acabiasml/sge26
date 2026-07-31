<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropUnique(['school_class_id', 'person_id']);
            $table->index(['school_class_id', 'person_id'], 'student_enrollments_class_person_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropIndex('student_enrollments_class_person_index');
            $table->unique(['school_class_id', 'person_id']);
        });
    }
};
