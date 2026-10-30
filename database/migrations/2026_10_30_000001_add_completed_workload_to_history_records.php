<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_history_records', function (Blueprint $table): void {
            $table->decimal('completed_workload_hours', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_records', function (Blueprint $table): void {
            $table->dropColumn('completed_workload_hours');
        });
    }
};
