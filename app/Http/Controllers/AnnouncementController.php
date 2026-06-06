<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        return view('announcements.index', [
            'announcements' => Announcement::query()
                ->with('school')
                ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $request->user()->manageableSchoolIds()))
                ->latest('starts_at')
                ->paginate(20),
            'schools' => $this->schools($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        abort_unless($request->user()->isAdministrator() || $request->user()->canManageSchool((int) ($data['school_id'] ?? 0)), 403);

        Announcement::query()->create($data + [
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('announcements.index')
            ->with('status', 'Recado cadastrado com sucesso.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->school_id === null
            ? $request->user()->isAdministrator()
            : $request->user()->canManageSchool($announcement->school_id), 403);

        $announcement->delete();

        return redirect()->route('announcements.index')
            ->with('status', 'Recado removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $rules = [
            'school_id' => [$request->user()->isAdministrator() ? 'nullable' : 'required', 'nullable', 'integer', Rule::exists('schools', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'highlight' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);
        $data['school_id'] = $data['school_id'] ?? null;
        $data['highlight'] = $request->boolean('highlight');
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
}
