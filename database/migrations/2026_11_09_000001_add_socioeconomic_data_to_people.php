<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->json('socioeconomic_data')->nullable());
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->dropColumn('socioeconomic_data'));
    }
};
