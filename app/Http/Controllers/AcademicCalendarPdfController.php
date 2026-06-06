<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\IssuedDocument;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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

        $pdf = Pdf::loadView('reports.academic-calendar', [
            'academicYear' => $academicYear,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'calendar' => $this->calendar($academicYear),
            'legend' => CalendarDay::printLegend(),
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'signatureDate' => $academicYear->approved_at ?? now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-calendario-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<int, array{name: string, days: array<int, string>}>
     */
    private function calendar(AcademicYear $academicYear): array
    {
        $days = $academicYear->days->keyBy(fn (CalendarDay $day): string => $day->date->toDateString());
        $periodStarts = $academicYear->periods->keyBy(fn ($period): string => $period->starts_at->toDateString());
        $periodEnds = $academicYear->periods->keyBy(fn ($period): string => $period->ends_at->toDateString());
        $months = [];

        foreach ($this->months() as $month => $monthName) {
            $monthDays = [];
            $daysInMonth = Carbon::create((int) $academicYear->reference_year, $month, 1)->daysInMonth;

            for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++) {
                if ($dayNumber > $daysInMonth) {
                    $monthDays[$dayNumber] = '';
                    continue;
                }

                $date = Carbon::create((int) $academicYear->reference_year, $month, $dayNumber);
                $dateKey = $date->toDateString();

                if ($periodStarts->has($dateKey)) {
                    $monthDays[$dayNumber] = 'IB';
                    continue;
                }

                if ($periodEnds->has($dateKey)) {
                    $monthDays[$dayNumber] = 'TB';
                    continue;
                }

                $monthDays[$dayNumber] = $this->dayCode($days->get($dateKey), $date);
            }

            $months[] = [
                'name' => $monthName,
                'days' => $monthDays,
            ];
        }

        return $months;
    }

    private function dayCode(?CalendarDay $calendarDay, Carbon $date): string
    {
        if ($calendarDay === null) {
            return $date->isSaturday() ? 'S' : ($date->isSunday() ? 'D' : '');
        }

        if ($calendarDay->type === CalendarDay::TYPE_WEEKEND) {
            return $date->isSaturday() ? 'S' : ($date->isSunday() ? 'D' : 'O');
        }

        return $calendarDay->printCode();
    }

    /**
     * @return array<int, string>
     */
    private function months(): array
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];
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
                'title' => 'Calendário escolar '.$academicYear->reference_year,
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
