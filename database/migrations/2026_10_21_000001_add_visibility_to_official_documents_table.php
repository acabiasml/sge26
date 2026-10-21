<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->string('visibility')->default('public')->index();
        });
    }

    public function down(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->dropColumn('visibility');
        });
    }
};
