<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->boolean('allow_diary_entries_outside_period')->default(false)->after('ignore_sundays');
        });
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->dropColumn('allow_diary_entries_outside_period');
        });
    }
};
