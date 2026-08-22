<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\IssuedDocument;
use App\Models\PersonSchoolRole;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AcademicCalendarPdfController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear): Response
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $academicYear->load(['school', 'days', 'periods']);
        $issuedDocument = $this->issuedDocument($request, $academicYear);
        $signatureDate = $academicYear->approved_at ?? now();

        $pdf = Pdf::loadView('reports.academic-calendar', [
            'academicYear' => $academicYear,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'calendar' => $this->calendar($academicYear),
            'legend' => CalendarDay::printLegend(),
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'signatureDate' => $signatureDate,
            'directorName' => $this->directorName($academicYear, $signatureDate),
            'calendarYearLabel' => $this->calendarYearLabel($academicYear),
            'periodSummary' => $this->periodSummary($academicYear),
            'specialDates' => $this->specialDates($academicYear),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('beaba-calendario-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<int, array{name: string, days: array<int, array{code: string, class: string, title: ?string}>}>
     */
    private function calendar(AcademicYear $academicYear): array
    {
        $days = $academicYear->days->keyBy(fn (CalendarDay $day): string => $day->date->toDateString());
        $periods = $academicYear->periods->sortBy('position')->values();
        $periodColorIndexes = $periods->mapWithKeys(fn ($period, int $index): array => [$period->id => ($index % 8) + 1]);
        $periodStarts = $periods->keyBy(fn ($period): string => $period->starts_at->toDateString());
        $periodEnds = $periods->keyBy(fn ($period): string => $period->ends_at->toDateString());
        $months = [];

        foreach ($this->monthsInPeriod($academicYear) as $month) {
            $monthDays = [];
            $daysInMonth = $month->daysInMonth;

            for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++) {
                if ($dayNumber > $daysInMonth) {
                    $monthDays[$dayNumber] = [
                        'code' => '',
                        'class' => 'empty',
                        'title' => null,
                    ];
                    continue;
                }

                $date = $month->copy()->day($dayNumber);
                $dateKey = $date->toDateString();
                $calendarDay = $days->get($dateKey);
                $period = $this->periodForDate($periods, $date, $calendarDay);

                if ($periodStarts->has($dateKey)) {
                    $markerPeriod = $periodStarts->get($dateKey);
                    $monthDays[$dayNumber] = [
                        'code' => 'IP',
                        'class' => $this->periodMarkerClass($markerPeriod, $periodColorIndexes->all(), 'period-start'),
                        'title' => $markerPeriod?->name,
                    ];
                    continue;
                }

                if ($periodEnds->has($dateKey)) {
                    $markerPeriod = $periodEnds->get($dateKey);
                    $monthDays[$dayNumber] = [
                        'code' => 'TP',
                        'class' => $this->periodMarkerClass($markerPeriod, $periodColorIndexes->all(), 'period-end'),
                        'title' => $markerPeriod?->name,
                    ];
                    continue;
                }

                $monthDays[$dayNumber] = $this->dayCell($calendarDay, $date, $period, $periodColorIndexes->all());
            }

            $months[] = [
                'name' => $this->monthLabel($month, $academicYear),
                'days' => $monthDays,
            ];
        }

        return $months;
    }

    /**
     * @return array{code: string, class: string, title: ?string}
     */
    private function dayCell(?CalendarDay $calendarDay, Carbon $date, $period = null, array $periodColorIndexes = []): array
    {
        if ($calendarDay === null) {
            return [
                'code' => $date->isSaturday() ? 'S' : ($date->isSunday() ? 'D' : ''),
                'class' => $date->isWeekend() ? 'weekend' : 'empty',
                'title' => null,
            ];
        }

        if ($calendarDay->type === CalendarDay::TYPE_WEEKEND) {
            return [
                'code' => $date->isSaturday() ? 'S' : ($date->isSunday() ? 'D' : 'O'),
                'class' => 'weekend',
                'title' => $calendarDay->title,
            ];
        }

        return [
            'code' => $calendarDay->printCode(),
            'class' => $this->dayClass($calendarDay, $period, $periodColorIndexes),
            'title' => $calendarDay->title,
        ];
    }

    private function dayClass(CalendarDay $calendarDay, $period = null, array $periodColorIndexes = []): string
    {
        if ($calendarDay->counts_as_school_day && $period && isset($periodColorIndexes[$period->id])) {
            return 'period-color-'.$periodColorIndexes[$period->id];
        }

        return match ($calendarDay->type) {
            CalendarDay::TYPE_SCHOOL_DAY => 'school-day',
            CalendarDay::TYPE_HOLIDAY => 'holiday',
            CalendarDay::TYPE_FINAL_VACATION, CalendarDay::TYPE_RECESS => 'recess',
            CalendarDay::TYPE_TRAINING, CalendarDay::TYPE_CLASS_COUNCIL => 'training',
            default => 'other',
        };
    }

    private function periodMarkerClass($period, array $periodColorIndexes, string $fallbackClass): string
    {
        if ($period && isset($periodColorIndexes[$period->id])) {
            return $fallbackClass.' period-color-'.$periodColorIndexes[$period->id];
        }

        return $fallbackClass;
    }

    private function periodForDate($periods, Carbon $date, ?CalendarDay $calendarDay)
    {
        if (! $calendarDay?->counts_as_school_day) {
            return null;
        }

        return $periods->first(fn ($period): bool => $date->betweenIncluded($period->starts_at, $period->ends_at));
    }

    /**
     * @return array<int, Carbon>
     */
    private function monthsInPeriod(AcademicYear $academicYear): array
    {
        $months = [];

        foreach (CarbonPeriod::create(
            $academicYear->starts_at->copy()->startOfMonth(),
            '1 month',
            $academicYear->ends_at->copy()->startOfMonth()
        ) as $month) {
            $months[] = $month->copy();
        }

        return $months;
    }

    private function monthLabel(Carbon $month, AcademicYear $academicYear): string
    {
        $label = ucfirst($month->translatedFormat('F'));

        if ($academicYear->starts_at->year !== $academicYear->ends_at->year) {
            return $label.'/'.$month->format('Y');
        }

        return $label;
    }

    private function calendarYearLabel(AcademicYear $academicYear): string
    {
        if ($academicYear->starts_at->year === $academicYear->ends_at->year) {
            return (string) $academicYear->starts_at->year;
        }

        return $academicYear->starts_at->year.'/'.$academicYear->ends_at->year;
    }

    /**
     * @return array<int, array{name: string, starts_at: string, ends_at: string, school_days: int}>
     */
    private function periodSummary(AcademicYear $academicYear): array
    {
        return $academicYear->periods
            ->sortBy('position')
            ->map(function ($period, int $index) use ($academicYear): array {
                $schoolDays = $academicYear->days
                    ->where('counts_as_school_day', true)
                    ->filter(fn (CalendarDay $day): bool => $day->date->betweenIncluded($period->starts_at, $period->ends_at))
                    ->count();

                return [
                    'name' => $period->name,
                    'starts_at' => $period->starts_at?->format('d/m/Y') ?? '-',
                    'ends_at' => $period->ends_at?->format('d/m/Y') ?? '-',
                    'school_days' => $schoolDays,
                    'color_class' => 'period-color-'.(($index % 8) + 1),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, description: string}>
     */
    private function specialDates(AcademicYear $academicYear): array
    {
        return collect($this->groupedCalendarSpecialDates($academicYear))
            ->sortBy('sort_date')
            ->take(14)
            ->map(fn (array $item): array => [
                'date' => $item['date'],
                'description' => $item['description'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, sort_date: string, description: string}>
     */
    private function groupedCalendarSpecialDates(AcademicYear $academicYear): array
    {
        $daysByDate = $academicYear->days->keyBy(fn (CalendarDay $day): string => $day->date->toDateString());
        $specialDays = $academicYear->days
            ->filter(fn (CalendarDay $day): bool => $day->type !== CalendarDay::TYPE_SCHOOL_DAY && $day->type !== CalendarDay::TYPE_WEEKEND && filled($this->specialDateName($day)))
            ->sortBy('date')
            ->values();

        $groups = [];
        $current = null;

        foreach ($specialDays as $day) {
            $specialDateName = $this->specialDateName($day);
            $key = $day->type.'|'.$specialDateName.'|'.$day->label();

            if (
                $current === null
                || $current['key'] !== $key
                || ! $this->canContinueSpecialDateGroup($current['end'], $day->date, $daysByDate)
            ) {
                if ($current !== null) {
                    $groups[] = $this->specialDateGroupToArray($current);
                }

                $current = [
                    'key' => $key,
                    'start' => $day->date->copy(),
                    'end' => $day->date->copy(),
                    'description' => $specialDateName.' - '.$day->label(),
                ];

                continue;
            }

            $current['end'] = $day->date->copy();
        }

        if ($current !== null) {
            $groups[] = $this->specialDateGroupToArray($current);
        }

        return $groups;
    }

    private function specialDateName(CalendarDay $day): ?string
    {
        $name = trim((string) ($day->title ?: $day->description ?: ''));

        return $name !== '' ? $name : null;
    }

    private function canContinueSpecialDateGroup(Carbon $currentEnd, Carbon $nextDate, $daysByDate): bool
    {
        $cursor = $currentEnd->copy()->addDay();

        while ($cursor->lt($nextDate)) {
            $betweenDay = $daysByDate->get($cursor->toDateString());

            if (! $cursor->isWeekend() && $betweenDay?->type !== CalendarDay::TYPE_WEEKEND) {
                return false;
            }

            $cursor->addDay();
        }

        return true;
    }

    private function specialDateGroupToArray(array $group): array
    {
        return [
            'date' => $this->dateRangeLabel($group['start'], $group['end']),
            'sort_date' => $group['start']->toDateString(),
            'description' => $group['description'],
        ];
    }

    private function dateRangeLabel(?Carbon $startsAt, ?Carbon $endsAt): string
    {
        if (! $startsAt) {
            return '-';
        }

        if (! $endsAt || $startsAt->isSameDay($endsAt)) {
            return $startsAt->format('d/m');
        }

        return $startsAt->format('d/m').' a '.$endsAt->format('d/m');
    }

    private function directorName(AcademicYear $academicYear, Carbon $date): ?string
    {
        $role = PersonSchoolRole::query()
            ->with('person')
            ->where('school_id', $academicYear->school_id)
            ->where('role', PersonSchoolRole::ROLE_MANAGER)
            ->where('position', PersonSchoolRole::POSITION_DIRECTOR)
            ->where('active', true)
            ->where(function ($query) use ($date): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $date->toDateString());
            })
            ->orderByDesc('started_at')
            ->first();

        return $role?->person?->social_name ?: $role?->person?->full_name;
    }

    private function issuedDocument(Request $request, AcademicYear $academicYear): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'academic-calendar',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Calendário escolar '.$this->calendarYearLabel($academicYear),
                'academic_year_id' => $academicYear->id,
                'school_id' => $academicYear->school_id,
                'school_days' => $academicYear->schoolDayCount(),
            ],
            'issued_at' => now(),
        ]);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

}
