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
    public static function canonicalQuery(array $query): array
    {
        $legacy = is_array($query['f'] ?? null) ? $query['f'] : [];
        $previous = array_replace($query, $legacy);
        $aliases = [
            'teacher_access_search' => 'docente',
            'teacher_access_school' => 'escola',
            'teacher_access_status' => 'acesso',
        ];
        foreach ($aliases as $original => $alias) {
            $value = array_key_exists($alias, $query) ? $query[$alias] : ($previous[$original] ?? null);
            unset($query[$original], $legacy[$original]);
            if ($value === null || $value === '' || ($alias === 'acesso' && $value === 'all')) {
                unset($query[$alias]);
            } else {
                $query[$alias] = $value;
            }
        }
        if (is_array($query['f'] ?? null)) {
            if ($legacy === []) {
                unset($query['f']);
            } else {
                $query['f'] = $legacy;
            }
        }

        return $query;
    }

    public function build(User $viewer, Request $request): ?array
    {
        abort_unless($viewer->canManagePeople(), 403);

        if (! UserLastAccess::available()) {
            return null;
        }

        $filters = $request->validate([
            'docente' => ['nullable', 'string', 'max:120'],
            'escola' => ['nullable', 'integer'],
            'acesso' => ['nullable', 'in:all,recent,overdue,missing'],
            'teacher_access_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $schools = School::query()->where('active', true)
            ->when(! $viewer->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $viewer->manageableSchoolIds()))
            ->orderBy('name')->get(['id', 'name']);
        $schoolId = ! empty($filters['escola']) ? (int) $filters['escola'] : null;
        abort_if($schoolId !== null && ! $schools->contains('id', $schoolId), 403);
        $schoolIds = $schoolId !== null ? [$schoolId] : $schools->modelKeys();
        $search = trim($filters['docente'] ?? '');
        $status = $filters['acesso'] ?? 'all';
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
