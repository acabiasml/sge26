<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('birth_city')->nullable()->after('birth_date');
            $table->string('birth_state', 2)->nullable()->after('birth_city');
            $table->string('nationality')->nullable()->after('birth_state');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['birth_city', 'birth_state', 'nationality']);
        });
    }
};
