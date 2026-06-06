<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarDayController extends Controller
{
    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'type' => ['required', Rule::in(array_keys(CalendarDay::TYPE_LABELS))],
            'counts_as_school_day' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['counts_as_school_day'] = $request->boolean('counts_as_school_day');

        $academicYear->days()->updateOrCreate(
            ['date' => $data['date']],
            $data
        );

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Dia do calendário salvo com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, CalendarDay $day): RedirectResponse
    {
        abort_unless($day->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $day->delete();

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Dia do calendário removido com sucesso.');
    }
}
