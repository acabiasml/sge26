<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarDayController extends Controller
{
    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'date_end' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'type' => ['required', Rule::in(array_keys(CalendarDay::TYPE_LABELS))],
            'counts_as_school_day' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $endDate = $data['date_end'] ?? $data['date'];
        $dates = CarbonPeriod::create($data['date'], $endDate);

        foreach ($dates as $date) {
            $dateString = $date->toDateString();

            $academicYear->days()->updateOrCreate(
                ['date' => $dateString],
                [
                    'date' => $dateString,
                    'type' => $data['type'],
                    'counts_as_school_day' => $request->boolean('counts_as_school_day'),
                    'title' => $data['title'] ?? null,
                    'description' => $data['description'] ?? null,
                ]
            );
        }

        $total = $dates->count();

        return redirect()->to(route('academic-years.show', $academicYear).'#section-calendario')
            ->with('status', $total === 1
                ? 'Dia do calendário salvo com sucesso.'
                : $total.' dias do calendário foram salvos com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, CalendarDay $day): RedirectResponse
    {
        abort_unless($day->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $day->delete();

        return redirect()->to(route('academic-years.show', $academicYear).'#section-calendario')
            ->with('status', 'Dia do calendário removido com sucesso.');
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if ($academicYear->isClosed()) {
            throw ValidationException::withMessages([
                'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar o calendário.',
            ]);
        }

        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => 'Calendário aprovado só pode ser alterado pela Administração global.',
        ]);
    }
}
