<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropUnique('people_legacy_id_unique');
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique('schools_legacy_id_unique');
            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropUnique(['legacy_source', 'legacy_id']);
            $table->unique('legacy_id');
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique(['legacy_source', 'legacy_id']);
            $table->unique('legacy_id');
        });
    }
};
