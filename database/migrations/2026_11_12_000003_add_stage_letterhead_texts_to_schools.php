<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->text('letterhead_text_elementary')->nullable();
            $table->text('letterhead_text_high_school')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn(['letterhead_text_elementary', 'letterhead_text_high_school']);
        });
    }
};
