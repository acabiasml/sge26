<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->timestamp('administrative_reopened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropColumn('administrative_reopened_at');
        });
    }
};
