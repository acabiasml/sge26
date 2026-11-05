<?php

namespace App\Support;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TeacherLastAccessOverview
{
    public function build(User $viewer, Request $request): ?array
    {
        abort_unless($viewer->canManagePeople(), 403);

        if (! UserLastAccess::available()) {
            return null;
        }

        $filters = $request->validate([
            'teacher_access_search' => ['nullable', 'string', 'max:120'],
            'teacher_access_school' => ['nullable', 'integer'],
            'teacher_access_status' => ['nullable', 'in:all,recent,overdue,missing'],
            'teacher_access_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $schools = School::query()->where('active', true)
            ->when(! $viewer->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $viewer->manageableSchoolIds()))
            ->orderBy('name')->get(['id', 'name']);
        $schoolId = ! empty($filters['teacher_access_school']) ? (int) $filters['teacher_access_school'] : null;
        abort_if($schoolId !== null && ! $schools->contains('id', $schoolId), 403);
        $schoolIds = $schoolId !== null ? [$schoolId] : $schools->modelKeys();
        $search = trim($filters['teacher_access_search'] ?? '');
        $status = $filters['teacher_access_status'] ?? 'all';
        $cutoff = CarbonImmutable::now()->subDays(7);
        $roles = fn (Builder $query): Builder => $query->where('role', PersonSchoolRole::ROLE_TEACHER)
            ->whereIn('school_id', $schoolIds)->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', now()->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', now()->toDateString()));
        $query = Person::query()->select(['people.id', 'people.full_name'])
            ->whereHas('schoolRoles', $roles)
            ->when($search !== '', fn (Builder $query) => $query->where('full_name', 'like', '%'.$search.'%'))
            ->with(['schoolRoles' => fn ($relation) => $roles($relation->getQuery())->with('school:id,name')])
            ->withMax('user as teacher_last_access_at', 'last_access_at')
            ->withExists('user as teacher_has_account');
        $known = fn (Builder $query): Builder => $query->whereNotNull('last_access_at');
        $recent = fn (Builder $query): Builder => $query->where('last_access_at', '>=', $cutoff);
        $total = (clone $query)->count();
        $recentCount = (clone $query)->whereHas('user', $recent)->count();
        $missingCount = (clone $query)->whereDoesntHave('user', $known)->count();

        match ($status) {
            'recent' => $query->whereHas('user', $recent),
            'overdue' => $query->whereHas('user', $known)->whereDoesntHave('user', $recent),
            'missing' => $query->whereDoesntHave('user', $known),
            default => $query,
        };

        $teachers = $query->orderBy('full_name')->paginate(10, ['*'], 'teacher_access_page')
            ->withQueryString()->fragment('teacher-access')
            ->through(function (Person $person) use ($cutoff): array {
                $lastAccess = $person->teacher_last_access_at !== null
                    ? CarbonImmutable::parse($person->teacher_last_access_at, config('app.timezone')) : null;

                return [
                    'person' => $person,
                    'schools' => $person->schoolRoles->pluck('school.name')->filter()->unique()->sort()->values(),
                    'last_access_at' => $lastAccess,
                    'status' => $lastAccess === null ? 'missing' : ($lastAccess->lt($cutoff) ? 'overdue' : 'recent'),
                    'has_account' => (bool) $person->teacher_has_account,
                ];
            });

        return [
            'teachers' => $teachers, 'schools' => $schools, 'school_id' => $schoolId,
            'search' => $search, 'status' => $status,
            'summary' => ['total' => $total, 'recent' => $recentCount, 'missing' => $missingCount,
                'overdue' => $total - $recentCount - $missingCount],
        ];
    }
}
