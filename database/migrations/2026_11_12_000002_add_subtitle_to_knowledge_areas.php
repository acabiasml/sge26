<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_areas', function (Blueprint $table): void {
            $table->text('subtitle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_areas', function (Blueprint $table): void {
            $table->dropColumn('subtitle');
        });
    }
};
