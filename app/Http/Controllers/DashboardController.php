<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $manageableSchoolIds = $user->isAdministrator() ? null : $user->manageableSchoolIds();

        $roleCounts = [
            PersonSchoolRole::ROLE_STUDENT => $this->activeRoleCount(PersonSchoolRole::ROLE_STUDENT, $manageableSchoolIds),
            PersonSchoolRole::ROLE_TEACHER => $this->activeRoleCount(PersonSchoolRole::ROLE_TEACHER, $manageableSchoolIds),
            PersonSchoolRole::ROLE_MANAGER => $this->activeRoleCount(PersonSchoolRole::ROLE_MANAGER, $manageableSchoolIds),
            PersonSchoolRole::ROLE_EMPLOYEE => $this->activeRoleCount(PersonSchoolRole::ROLE_EMPLOYEE, $manageableSchoolIds),
        ];

        return view('dashboard', [
            'schoolCount' => $this->schoolCount($manageableSchoolIds),
            'personCount' => $this->personCount($manageableSchoolIds),
            'roleCounts' => $roleCounts,
            'roleChart' => $this->roleChart($roleCounts),
            'studentsBySchoolChart' => $this->studentsBySchoolChart($manageableSchoolIds),
            'birthdays' => $this->birthdays($manageableSchoolIds),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     */
    private function schoolCount(?array $schoolIds): int
    {
        return School::query()
            ->where('active', true)
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('id', $schoolIds))
            ->count();
    }

    /**
     * @param list<int>|null $schoolIds
     */
    private function personCount(?array $schoolIds): int
    {
        return Person::query()
            ->where('active', true)
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles)->whereIn('school_id', $schoolIds));
            })
            ->count();
    }

    /**
     * @param list<int>|null $schoolIds
     */
    private function activeRoleCount(string $role, ?array $schoolIds): int
    {
        return $this->activeRoleScope(PersonSchoolRole::query())
            ->where('role', $role)
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->distinct('person_id')
            ->count('person_id');
    }

    private function activeRoleScope(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereHas('person', fn (Builder $person) => $person->where('active', true))
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }

    /**
     * @param array<string, int> $roleCounts
     * @return array{labels: list<string>, values: list<int>}
     */
    private function roleChart(array $roleCounts): array
    {
        return [
            'labels' => collect($roleCounts)->keys()
                ->map(fn (string $role): string => PersonSchoolRole::ROLE_LABELS[$role] ?? $role)
                ->values()
                ->all(),
            'values' => array_values($roleCounts),
        ];
    }

    /**
     * @param list<int>|null $schoolIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function studentsBySchoolChart(?array $schoolIds): array
    {
        $rows = $this->activeRoleScope(PersonSchoolRole::query())
            ->selectRaw('school_id, count(distinct person_id) as total')
            ->where('role', PersonSchoolRole::ROLE_STUDENT)
            ->whereNotNull('school_id')
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->groupBy('school_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('school')
            ->get();

        return [
            'labels' => $rows->map(fn (PersonSchoolRole $role): string => $role->school?->name ?? 'Sem escola')->all(),
            'values' => $rows->map(fn (PersonSchoolRole $role): int => (int) $role->total)->all(),
        ];
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, Person>
     */
    private function birthdays(?array $schoolIds): Collection
    {
        return Person::query()
            ->where('active', true)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', now()->month)
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles)->whereIn('school_id', $schoolIds));
            })
            ->limit(8)
            ->get()
            ->sortBy(fn (Person $person): int => (int) $person->birth_date?->format('d'))
            ->values();
    }
}
