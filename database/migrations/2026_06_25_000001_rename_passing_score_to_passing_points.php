<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            if (Schema::hasColumn('academic_years', 'passing_score') && ! Schema::hasColumn('academic_years', 'passing_points')) {
                $table->renameColumn('passing_score', 'passing_points');
            }
        });

        if (Schema::hasColumn('academic_years', 'passing_points')) {
            DB::table('academic_years')->update([
                'passing_points' => DB::raw('passing_points * 4'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('academic_years', 'passing_points')) {
            DB::table('academic_years')->update([
                'passing_points' => DB::raw('passing_points / 4'),
            ]);
        }

        Schema::table('academic_years', function (Blueprint $table): void {
            if (Schema::hasColumn('academic_years', 'passing_points') && ! Schema::hasColumn('academic_years', 'passing_score')) {
                $table->renameColumn('passing_points', 'passing_score');
            }
        });
    }
};
