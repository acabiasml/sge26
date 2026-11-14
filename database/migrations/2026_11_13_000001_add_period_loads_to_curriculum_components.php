<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_components', fn (Blueprint $table) => $table->json('period_loads')->nullable());
    }

    public function down(): void
    {
        Schema::table('curriculum_components', fn (Blueprint $table) => $table->dropColumn('period_loads'));
    }
};
