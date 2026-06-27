<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dropAnnualLessons = Schema::hasColumn('curriculum_components', 'annual_lessons');
        $dropSortOrder = Schema::hasColumn('curriculum_components', 'sort_order');

        if ($dropAnnualLessons || $dropSortOrder) {
            Schema::table('curriculum_components', function (Blueprint $table) use ($dropAnnualLessons, $dropSortOrder): void {
                if ($dropAnnualLessons) {
                    $table->dropColumn('annual_lessons');
                }

                if ($dropSortOrder) {
                    $table->dropColumn('sort_order');
                }
            });
        }
    }

    public function down(): void
    {
        $addAnnualLessons = ! Schema::hasColumn('curriculum_components', 'annual_lessons');
        $addSortOrder = ! Schema::hasColumn('curriculum_components', 'sort_order');

        if ($addAnnualLessons || $addSortOrder) {
            Schema::table('curriculum_components', function (Blueprint $table) use ($addAnnualLessons, $addSortOrder): void {
                if ($addAnnualLessons) {
                    $table->unsignedInteger('annual_lessons')->nullable()->after('weekly_lessons');
                }

                if ($addSortOrder) {
                    $table->unsignedSmallInteger('sort_order')->default(0)->after('workload_hours');
                }
            });
        }
    }
};
