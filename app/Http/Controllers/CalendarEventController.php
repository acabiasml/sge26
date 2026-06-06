<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return view('calendar-events.index', [
            'events' => CalendarEvent::query()
                ->with(['school', 'academicYear'])
                ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $request->user()->manageableSchoolIds()))
                ->orderBy('starts_at')
                ->paginate(20),
            'schools' => $this->schools($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        abort_unless($request->user()->canManageSchool((int) $data['school_id']), 403);

        CalendarEvent::query()->create($data + [
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('calendar-events.index')
            ->with('status', 'Evento cadastrado com sucesso.');
    }

    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($event->school_id), 403);

        $event->delete();

        return redirect()->route('calendar-events.index')
            ->with('status', 'Evento removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'category' => ['required', 'string', 'max:255'],
            'highlight' => ['nullable', 'boolean'],
        ]);

        $data['all_day'] = $request->boolean('all_day', true);
        $data['highlight'] = $request->boolean('highlight');

        if (! empty($data['academic_year_id'])) {
            $yearSchoolId = AcademicYear::query()->whereKey($data['academic_year_id'])->value('school_id');
            abort_unless((int) $yearSchoolId === (int) $data['school_id'], 422);
        }

        return $data;
    }

    private function schools(Request $request)
    {
        return School::query()
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
            ->with('academicYears')
            ->orderBy('name')
            ->get();
    }
}
