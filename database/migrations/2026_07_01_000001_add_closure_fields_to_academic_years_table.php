<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('approved_at');
            $table->foreignId('closed_by_person_id')
                ->nullable()
                ->after('closed_at')
                ->constrained('people')
                ->nullOnDelete();
            $table->text('closure_notes')->nullable()->after('closed_by_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropForeign(['closed_by_person_id']);
            $table->dropColumn(['closed_at', 'closed_by_person_id', 'closure_notes']);
        });
    }
};
