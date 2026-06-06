<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicPeriodController extends Controller
{
    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'position' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $overlaps = $academicYear->periods()
            ->where(function (Builder $query) use ($data): void {
                $query->whereDate('starts_at', '<=', $data['ends_at'])
                    ->whereDate('ends_at', '>=', $data['starts_at']);
            })
            ->exists();

        if ($overlaps) {
            return back()->withErrors(['starts_at' => 'O período informado se sobrepõe a outro período deste ano letivo.'])->withInput();
        }

        $academicYear->periods()->create($data);

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Período cadastrado com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $period->delete();

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Período removido com sucesso.');
    }
}
