<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->foreignId('cancelled_by_person_id')
                ->nullable()
                ->after('transferred_by_person_id')
                ->constrained('people')
                ->nullOnDelete();
            $table->date('cancelled_at')->nullable()->after('transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_person_id');
            $table->dropColumn('cancelled_at');
        });
    }
};
