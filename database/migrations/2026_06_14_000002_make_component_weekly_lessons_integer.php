<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('curriculum_components')
            ->whereNotNull('weekly_lessons')
            ->orderBy('id')
            ->chunkById(100, function ($components): void {
                foreach ($components as $component) {
                    DB::table('curriculum_components')
                        ->where('id', $component->id)
                        ->update(['weekly_lessons' => (int) round((float) $component->weekly_lessons)]);
                }
            });

        Schema::table('curriculum_components', function (Blueprint $table): void {
            $table->unsignedSmallInteger('weekly_lessons')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_components', function (Blueprint $table): void {
            $table->decimal('weekly_lessons', 5, 2)->nullable()->change();
        });
    }
};
