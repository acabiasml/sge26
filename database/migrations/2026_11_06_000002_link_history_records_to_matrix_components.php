<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_academic_history_records', 'curriculum_component_id')) {
            Schema::table('student_academic_history_records', fn (Blueprint $table) => $table->foreignId('curriculum_component_id')->nullable()->constrained(indexName: 'history_record_matrix_fk')->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::table('student_academic_history_records', function (Blueprint $table): void {
            $table->dropForeign('history_record_matrix_fk');
            $table->dropColumn('curriculum_component_id');
        });
    }
};
