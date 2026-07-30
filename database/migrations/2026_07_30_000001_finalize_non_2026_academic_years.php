<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $activeYearIds = DB::table('academic_years')
            ->whereYear('ends_at', 2026)
            ->orWhereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('calendar_days')
                    ->whereColumn('calendar_days.academic_year_id', 'academic_years.id')
                    ->where('calendar_days.counts_as_school_day', true)
                    ->whereBetween('calendar_days.date', ['2026-01-01', '2026-12-31']);
            })
            ->pluck('id');

        DB::table('academic_years')
            ->whereIn('id', $activeYearIds)
            ->update([
                'active' => true,
                'closed_at' => null,
                'closed_by_person_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('academic_years')
            ->whereNotIn('id', $activeYearIds)
            ->orderBy('id')
            ->get(['id', 'ends_at'])
            ->each(function (object $academicYear) use ($now): void {
                $closedAt = $academicYear->ends_at
                    ? Carbon::parse($academicYear->ends_at)->endOfDay()
                    : $now;

                DB::table('academic_years')
                    ->where('id', $academicYear->id)
                    ->update([
                        'active' => false,
                        'closed_at' => $closedAt,
                        'closed_by_person_id' => null,
                        'updated_at' => $now,
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
