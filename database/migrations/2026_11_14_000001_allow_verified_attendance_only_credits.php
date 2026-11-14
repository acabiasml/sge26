<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_period_convalidations', function (Blueprint $table): void {
            $table->decimal('score', 4, 1)->nullable()->change();
            $table->unsignedInteger('verified_diary_attended_minutes')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('student_period_convalidations')->whereNull('score')->exists()) {
            throw new RuntimeException('Há aproveitamentos somente de frequência; preserve esses registros antes de reverter.');
        }
        Schema::table('student_period_convalidations', function (Blueprint $table): void {
            $table->decimal('score', 4, 1)->nullable(false)->change();
            $table->dropColumn('verified_diary_attended_minutes');
        });
    }
};
