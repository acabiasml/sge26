<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('school_classes', 'starts_period_id')) {
            Schema::table('school_classes', function (Blueprint $table): void {
                $table->foreignId('starts_period_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained('academic_periods')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('school_classes', 'ends_period_id')) {
            Schema::table('school_classes', function (Blueprint $table): void {
                $table->foreignId('ends_period_id')
                    ->nullable()
                    ->after('starts_period_id')
                    ->constrained('academic_periods')
                    ->nullOnDelete();
            });
        }

        DB::table('school_classes')
            ->orderBy('id')
            ->get()
            ->each(function (object $class): void {
                $periods = DB::table('academic_periods')
                    ->where('academic_year_id', $class->academic_year_id)
                    ->orderBy('position')
                    ->orderBy('starts_at')
                    ->get(['id', 'starts_at', 'ends_at']);

                if ($periods->isEmpty()) {
                    return;
                }

                $validPeriodIds = $periods->pluck('id')->map(fn ($id): int => (int) $id);
                $startsPeriodId = $this->validPeriodId($class->starts_period_id ?? null, $validPeriodIds)
                    ?? $this->periodForDate($periods, $class->starts_at ?? null)
                    ?? (int) $periods->first()->id;
                $endsPeriodId = $this->validPeriodId($class->ends_period_id ?? null, $validPeriodIds)
                    ?? $this->periodForDate($periods, $class->ends_at ?? null)
                    ?? (int) $periods->last()->id;

                DB::table('school_classes')
                    ->where('id', $class->id)
                    ->update([
                        'starts_period_id' => $startsPeriodId,
                        'ends_period_id' => $endsPeriodId,
                    ]);
            });

        $columnsToDrop = collect(['starts_at', 'ends_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('school_classes', $column))
            ->all();

        if ($columnsToDrop !== []) {
            Schema::table('school_classes', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('school_classes', 'starts_at')) {
            Schema::table('school_classes', function (Blueprint $table): void {
                $table->date('starts_at')->nullable()->after('shift');
            });
        }

        if (! Schema::hasColumn('school_classes', 'ends_at')) {
            Schema::table('school_classes', function (Blueprint $table): void {
                $table->date('ends_at')->nullable()->after('starts_at');
            });
        }

        DB::table('school_classes')
            ->leftJoin('academic_periods as starts_period', 'starts_period.id', '=', 'school_classes.starts_period_id')
            ->leftJoin('academic_periods as ends_period', 'ends_period.id', '=', 'school_classes.ends_period_id')
            ->leftJoin('academic_years', 'academic_years.id', '=', 'school_classes.academic_year_id')
            ->select([
                'school_classes.id',
                DB::raw('COALESCE(starts_period.starts_at, academic_years.starts_at) as effective_starts_at'),
                DB::raw('COALESCE(ends_period.ends_at, academic_years.ends_at) as effective_ends_at'),
            ])
            ->orderBy('school_classes.id')
            ->get()
            ->each(function (object $class): void {
                DB::table('school_classes')
                    ->where('id', $class->id)
                    ->update([
                        'starts_at' => $class->effective_starts_at,
                        'ends_at' => $class->effective_ends_at,
                    ]);
            });
    }

    private function validPeriodId(mixed $periodId, Collection $validPeriodIds): ?int
    {
        if (! $periodId || ! $validPeriodIds->contains((int) $periodId)) {
            return null;
        }

        return (int) $periodId;
    }

    private function periodForDate(Collection $periods, mixed $date): ?int
    {
        if (! $date) {
            return null;
        }

        $period = $periods->first(
            fn (object $candidate): bool => $candidate->starts_at <= $date && $candidate->ends_at >= $date,
        );

        return $period ? (int) $period->id : null;
    }
};
