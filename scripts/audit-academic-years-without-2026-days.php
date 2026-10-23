<?php

use App\Models\AcademicYear;
use App\Support\AcademicYearClosureStatus;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$closureStatus = $app->make(AcademicYearClosureStatus::class);

AcademicYear::query()
    ->with('school')
    ->orderBy('school_id')
    ->orderBy('reference_year')
    ->orderBy('id')
    ->get()
    ->each(function (AcademicYear $year) use ($closureStatus): void {
        $daysIn2026 = $year->days()
            ->where('counts_as_school_day', true)
            ->whereBetween('date', ['2026-01-01', '2026-12-31'])
            ->count();

        $row = [
            'id' => $year->id,
            'school_id' => $year->school_id,
            'school' => $year->school?->name,
            'year' => $year->reference_year,
            'name' => $year->name,
            'ends_at' => $year->ends_at?->toDateString(),
            'school_days_2026' => $daysIn2026,
            'closed_at' => $year->closed_at?->toDateTimeString(),
            'active' => $year->active,
        ];

        if ($daysIn2026 === 0 && ! $year->isClosed()) {
            $row['closure_errors'] = collect($closureStatus->issues($year))
                ->where('level', 'error')
                ->pluck('message')
                ->values()
                ->all();
        }

        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    });
