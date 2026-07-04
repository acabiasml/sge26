<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_alerts', function (Blueprint $table): void {
            $table->timestamp('dismissed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('diary_alerts', function (Blueprint $table): void {
            $table->dropColumn('dismissed_at');
        });
    }
};
