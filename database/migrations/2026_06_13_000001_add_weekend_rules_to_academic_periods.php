<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->boolean('ignore_saturdays')->default(true)->after('ends_at');
            $table->boolean('ignore_sundays')->default(true)->after('ignore_saturdays');
        });
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->dropColumn(['ignore_saturdays', 'ignore_sundays']);
        });
    }
};
