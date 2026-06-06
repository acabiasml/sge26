<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\School;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $years = AcademicYear::query()
            ->with('school')
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $request->user()->manageableSchoolIds()))
            ->orderByDesc('reference_year')
            ->orderBy('name')
            ->paginate(15);

        return view('academic-years.index', [
            'years' => $years,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return view('academic-years.create', [
            'schools' => $this->schools($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        abort_unless($request->user()->canManageSchool((int) $data['school_id']), 403);
        $calendarRecesses = $this->normalizedRecesses($request, new AcademicYear($data));

        $year = AcademicYear::query()->create($data);
        $this->generateInitialCalendar($year, $request->boolean('ignore_weekends'), $calendarRecesses);

        return redirect()->route('academic-years.show', $year)
            ->with('status', 'Ano letivo cadastrado com sucesso.');
    }

    public function show(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-years.show', [
            'academicYear' => $academicYear->load(['school', 'periods', 'days', 'events']),
        ]);
    }

    public function edit(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-years.edit', [
            'academicYear' => $academicYear,
            'schools' => $this->schools($request),
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $data = $this->validatedData($request, $academicYear);
        abort_unless($request->user()->canManageSchool((int) $data['school_id']), 403);

        $academicYear->update($data);

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Ano letivo atualizado com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        if ($academicYear->periods()->exists() || $academicYear->events()->exists()) {
            return redirect()->route('academic-years.index')
                ->with('status', 'Não é possível excluir um ano letivo que possui períodos ou eventos associados.');
        }

        $academicYear->delete();

        return redirect()->route('academic-years.index')
            ->with('status', 'Ano letivo excluído com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?AcademicYear $academicYear = null): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'reference_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'approved_at' => ['nullable', 'date'],
            'class_hour_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'minimum_school_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }

    private function schools(Request $request)
    {
        return School::query()
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{starts_at: Carbon, ends_at: Carbon, title: string}>
     */
    private function normalizedRecesses(Request $request, AcademicYear $academicYear): array
    {
        $data = $request->validate([
            'ignore_weekends' => ['nullable', 'boolean'],
            'recesses' => ['nullable', 'array'],
            'recesses.*.starts_at' => ['nullable', 'date'],
            'recesses.*.ends_at' => ['nullable', 'date'],
            'recesses.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $yearStart = $academicYear->starts_at->copy()->startOfDay();
        $yearEnd = $academicYear->ends_at->copy()->startOfDay();
        $recesses = [];

        foreach ($data['recesses'] ?? [] as $recess) {
            if (blank($recess['starts_at'] ?? null) && blank($recess['ends_at'] ?? null)) {
                continue;
            }

            if (blank($recess['starts_at'] ?? null) || blank($recess['ends_at'] ?? null)) {
                throw ValidationException::withMessages([
                    'recesses' => 'Informe início e fim para cada período de recesso preenchido.',
                ]);
            }

            $startsAt = Carbon::parse($recess['starts_at'])->startOfDay();
            $endsAt = Carbon::parse($recess['ends_at'])->startOfDay();

            if ($startsAt->lt($yearStart) || $endsAt->gt($yearEnd) || $startsAt->gt($endsAt)) {
                throw ValidationException::withMessages([
                    'recesses' => 'Os recessos precisam estar dentro do período do ano letivo e ter fim igual ou posterior ao início.',
                ]);
            }

            $recesses[] = [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'title' => filled($recess['title'] ?? null) ? $recess['title'] : 'Recesso escolar',
            ];
        }

        return $recesses;
    }

    /**
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, title: string}>  $recesses
     */
    private function generateInitialCalendar(AcademicYear $academicYear, bool $ignoreWeekends, array $recesses): void
    {
        $now = now();
        $rows = [];

        foreach (CarbonPeriod::create($academicYear->starts_at, $academicYear->ends_at) as $date) {
            $recess = $this->recessForDate($date, $recesses);

            if ($recess !== null) {
                $type = CalendarDay::TYPE_RECESS;
                $countsAsSchoolDay = false;
                $title = $recess['title'];
            } elseif ($ignoreWeekends && $date->isWeekend()) {
                $type = CalendarDay::TYPE_WEEKEND;
                $countsAsSchoolDay = false;
                $title = 'Fim de semana';
            } else {
                $type = CalendarDay::TYPE_SCHOOL_DAY;
                $countsAsSchoolDay = true;
                $title = null;
            }

            $rows[] = [
                'academic_year_id' => $academicYear->id,
                'date' => $date->toDateString(),
                'type' => $type,
                'counts_as_school_day' => $countsAsSchoolDay,
                'title' => $title,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            CalendarDay::query()->insert($rows);
        }
    }

    /**
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, title: string}>  $recesses
     * @return array{starts_at: Carbon, ends_at: Carbon, title: string}|null
     */
    private function recessForDate(Carbon $date, array $recesses): ?array
    {
        foreach ($recesses as $recess) {
            if ($date->betweenIncluded($recess['starts_at'], $recess['ends_at'])) {
                return $recess;
            }
        }

        return null;
    }
}
