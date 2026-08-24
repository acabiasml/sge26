<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\Person;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AcademicCalendarGrid
{
    public static function forAcademicYear(AcademicYear $academicYear): Collection
    {
        $months = collect();
        $cursor = $academicYear->starts_at->copy()->startOfMonth();
        $last = $academicYear->ends_at->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $months->push(self::forMonth($academicYear, $cursor));
            $cursor->addMonth();
        }

        return $months;
    }

    public static function forMonth(AcademicYear $academicYear, Carbon $month): array
    {
        $days = $academicYear->days->keyBy(fn (CalendarDay $day): string => $day->date->toDateString());
        $periods = $academicYear->periods->sortBy('position')->values();

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $weeks = collect();
        $week = collect();

        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $date) {
            $day = $days->get($date->toDateString());
            $periodIndex = self::periodIndexForDate($periods, $date, $day);
            $periodMarker = self::periodMarkerForDate($periods, $date);

            $week->push([
                'date' => $date->copy(),
                'in_month' => $date->betweenIncluded($monthStart, $monthEnd),
                'in_academic_year' => $date->betweenIncluded($academicYear->starts_at, $academicYear->ends_at),
                'day' => $day,
                'code' => $periodMarker['code'] ?? self::codeForDate($date, $day),
                'period_index' => $periodIndex,
                'period_class' => $periodIndex ? 'sge-calendar-period-'.$periodIndex : null,
                'label' => self::labelForDate($date, $day, $periodMarker),
            ]);

            if ($week->count() === 7) {
                $weeks->push($week);
                $week = collect();
            }
        }

        return [
            'label' => ucfirst($monthStart->translatedFormat('F/Y')),
            'weeks' => $weeks,
        ];
    }

    /**
     * @param Collection<int, AcademicYear> $academicYears
     * @param Collection<int, Person> $birthdays
     */
    public static function combinedMonth(Collection $academicYears, Collection $birthdays, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $weeks = collect();
        $week = collect();

        $daysByDate = $academicYears
            ->flatMap(fn (AcademicYear $academicYear): Collection => $academicYear->days->map(function (CalendarDay $day) use ($academicYear): array {
                $periods = $academicYear->periods->sortBy('position')->values();
                $periodIndex = self::periodIndexForDate($periods, $day->date, $day);
                $periodMarker = self::periodMarkerForDate($periods, $day->date);

                return [
                    'academic_year' => $academicYear,
                    'school' => $academicYear->school,
                    'day' => $day,
                    'period_index' => $periodIndex,
                    'period_class' => $periodIndex ? 'sge-calendar-period-'.$periodIndex : null,
                    'period_marker' => $periodMarker,
                ];
            }))
            ->groupBy(fn (array $entry): string => $entry['day']->date->toDateString());

        $birthdaysByDate = $birthdays
            ->filter(fn (Person $person): bool => filled($person->birth_date))
            ->groupBy(fn (Person $person): string => $month->copy()->day((int) $person->birth_date->format('d'))->toDateString());

        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $date) {
            $dayEntries = $daysByDate->get($date->toDateString(), collect());
            $birthdayEntries = $birthdaysByDate->get($date->toDateString(), collect());
            $codes = self::combinedCodes($date, $dayEntries);

            $week->push([
                'date' => $date->copy(),
                'in_month' => $date->betweenIncluded($monthStart, $monthEnd),
                'day_entries' => $dayEntries,
                'birthdays' => $birthdayEntries,
                'codes' => $codes,
                'primary_type' => self::primaryType($date, $dayEntries),
                'period_class' => self::primaryPeriodClass($dayEntries),
                'counts_as_school_day' => $dayEntries->contains(fn (array $entry): bool => (bool) $entry['day']->counts_as_school_day),
                'has_birthdays' => $birthdayEntries->isNotEmpty(),
                'label' => self::combinedLabel($date, $dayEntries, $birthdayEntries),
            ]);

            if ($week->count() === 7) {
                $weeks->push($week);
                $week = collect();
            }
        }

        return [
            'label' => ucfirst($monthStart->translatedFormat('F/Y')),
            'weeks' => $weeks,
            'school_days_count' => $academicYears
                ->flatMap(fn (AcademicYear $academicYear): Collection => $academicYear->days->where('counts_as_school_day', true))
                ->map(fn (CalendarDay $day): string => $day->date->toDateString())
                ->unique()
                ->count(),
            'birthdays_count' => $birthdays->count(),
        ];
    }

    private static function codeForDate(Carbon $date, ?CalendarDay $day): string
    {
        if ($day) {
            return $day->printCode() ?: ($date->isSunday() ? 'D' : ($date->isSaturday() ? 'S' : ''));
        }

        return $date->isSunday() ? 'D' : ($date->isSaturday() ? 'S' : '');
    }

    private static function combinedCodes(Carbon $date, Collection $dayEntries): Collection
    {
        if ($dayEntries->isEmpty()) {
            return collect([self::codeForDate($date, null)])->filter()->values();
        }

        return $dayEntries
            ->map(fn (array $entry): string => $entry['period_marker']['code'] ?? self::codeForDate($date, $entry['day']))
            ->filter()
            ->unique()
            ->values();
    }

    private static function primaryType(Carbon $date, Collection $dayEntries): string
    {
        if ($dayEntries->contains(fn (array $entry): bool => (bool) $entry['day']->counts_as_school_day)) {
            return CalendarDay::TYPE_SCHOOL_DAY;
        }

        return $dayEntries->first()['day']->type ?? ($date->isWeekend() ? CalendarDay::TYPE_WEEKEND : 'empty');
    }

    private static function primaryPeriodClass(Collection $dayEntries): ?string
    {
        $entry = $dayEntries->first(fn (array $entry): bool => filled($entry['period_class'] ?? null));

        return $entry['period_class'] ?? null;
    }

    private static function periodIndexForDate(Collection $periods, Carbon $date, ?CalendarDay $day): ?int
    {
        if (! $day?->counts_as_school_day) {
            return null;
        }

        $period = $periods->first(fn ($period): bool => $date->betweenIncluded($period->starts_at, $period->ends_at));

        if (! $period) {
            return null;
        }

        $index = $periods->search(fn ($candidate): bool => $candidate->id === $period->id);

        return is_int($index) ? ($index % 8) + 1 : null;
    }

    private static function periodMarkerForDate(Collection $periods, Carbon $date): ?array
    {
        $period = $periods->first(fn ($period): bool => $period->starts_at->isSameDay($date));

        if ($period) {
            return ['code' => 'IP', 'label' => 'Início de '.$period->name];
        }

        $period = $periods->first(fn ($period): bool => $period->ends_at->isSameDay($date));

        if ($period) {
            return ['code' => 'TP', 'label' => 'Término de '.$period->name];
        }

        return null;
    }

    private static function labelForDate(Carbon $date, ?CalendarDay $day, ?array $periodMarker = null): string
    {
        $label = $day?->label() ?? ($date->isSunday() ? 'Domingo' : ($date->isSaturday() ? 'Sábado' : 'Sem registro'));

        return $periodMarker ? $periodMarker['label'].' - '.$label : $label;
    }

    private static function combinedLabel(Carbon $date, Collection $dayEntries, Collection $birthdays): string
    {
        $parts = collect();

        if ($dayEntries->isEmpty()) {
            $parts->push($date->isSunday() ? 'Domingo' : ($date->isSaturday() ? 'Sábado' : 'Sem registro'));
        } else {
            $dayEntries->each(function (array $entry) use ($parts): void {
                $day = $entry['day'];
                $school = $entry['school']?->name;
                $parts->push(trim(self::labelForDate($day->date, $day, $entry['period_marker'] ?? null).($school ? ' - '.$school : '')));

                if (filled($day->title)) {
                    $parts->push(trim($day->title.($school ? ' - '.$school : '')));
                } elseif (filled($day->description)) {
                    $parts->push(trim($day->description.($school ? ' - '.$school : '')));
                }
            });
        }

        $birthdays->each(fn (Person $person) => $parts->push('Aniversário: '.($person->social_name ?: $person->full_name)));

        return $parts->unique()->join(' | ');
    }
}
