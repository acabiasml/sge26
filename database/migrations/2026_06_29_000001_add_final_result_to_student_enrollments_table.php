<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->string('final_result_status')->nullable()->after('type');
            $table->json('final_result_details')->nullable()->after('final_result_status');
            $table->timestamp('final_result_calculated_at')->nullable()->after('final_result_details');
            $table->foreignId('final_result_calculated_by_person_id')
                ->nullable()
                ->after('final_result_calculated_at')
                ->constrained('people')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('final_result_calculated_by_person_id');
            $table->dropColumn([
                'final_result_status',
                'final_result_details',
                'final_result_calculated_at',
            ]);
        });
    }
};
