<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CalendarDay;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Support\AcademicCalendarGrid;
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
        $visibleSchoolIds = $user->isAdministrator() ? null : $user->visibleSchoolIds();
        $calendarSchoolIds = $user->isAdministrator()
            ? null
            : ($user->isManager() ? $manageableSchoolIds : $visibleSchoolIds);

        $roleCounts = [
            PersonSchoolRole::ROLE_STUDENT => $this->activeRoleCount(PersonSchoolRole::ROLE_STUDENT, $manageableSchoolIds),
            PersonSchoolRole::ROLE_TEACHER => $this->activeRoleCount(PersonSchoolRole::ROLE_TEACHER, $manageableSchoolIds),
            PersonSchoolRole::ROLE_MANAGER => $this->activeRoleCount(PersonSchoolRole::ROLE_MANAGER, $manageableSchoolIds),
            PersonSchoolRole::ROLE_EMPLOYEE => $this->activeRoleCount(PersonSchoolRole::ROLE_EMPLOYEE, $manageableSchoolIds),
        ];

        $birthdays = $this->birthdays($manageableSchoolIds);
        $monthAcademicCalendars = $this->monthAcademicCalendars($calendarSchoolIds);

        return view('dashboard', [
            'schoolCount' => $this->schoolCount($manageableSchoolIds),
            'personCount' => $this->personCount($manageableSchoolIds),
            'activeEnrollmentCount' => $this->activeEnrollmentCount($manageableSchoolIds),
            'activeAcademicYearCount' => $this->activeAcademicYearCount($calendarSchoolIds),
            'registrationPendingCount' => $this->registrationPendingCount($manageableSchoolIds),
            'monthSchoolDayCount' => $monthAcademicCalendars->sum(fn (AcademicYear $academicYear): int => $academicYear->days->where('counts_as_school_day', true)->count()),
            'roleCounts' => $roleCounts,
            'roleChart' => $this->roleChart($roleCounts),
            'studentsBySchoolChart' => $this->studentsBySchoolChart($manageableSchoolIds),
            'calendarTypeChart' => $this->calendarTypeChart($monthAcademicCalendars),
            'birthdays' => $birthdays,
            'announcements' => $this->announcements($visibleSchoolIds),
            'monthAcademicCalendars' => $monthAcademicCalendars,
            'combinedCalendarMonth' => AcademicCalendarGrid::combinedMonth($monthAcademicCalendars, $birthdays, now('America/Sao_Paulo')),
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

    /**
     * @param list<int>|null $schoolIds
     */
    private function activeEnrollmentCount(?array $schoolIds): int
    {
        return StudentEnrollment::query()
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->whereHas('student', fn (Builder $query) => $query->where('active', true))
            ->whereHas('schoolClass.academicYear', function (Builder $query) use ($schoolIds): void {
                $query->where('active', true)
                    ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds));
            })
            ->count();
    }

    /**
     * @param list<int>|null $schoolIds
     */
    private function activeAcademicYearCount(?array $schoolIds): int
    {
        return AcademicYear::query()
            ->where('active', true)
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->whereDate('ends_at', '>=', now()->toDateString())
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->count();
    }

    /**
     * @param list<int>|null $schoolIds
     */
    private function registrationPendingCount(?array $schoolIds): int
    {
        return Person::query()
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('cpf')
                    ->orWhere('cpf', '')
                    ->orWhereNull('institutional_email')
                    ->orWhere('institutional_email', '')
                    ->orWhereNull('birth_date')
                    ->orWhereNull('mother_name')
                    ->orWhere('mother_name', '')
                    ->orWhereNull('phone')
                    ->orWhere('phone', '')
                    ->orWhereNull('profile_completed_at');
            })
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles)->whereIn('school_id', $schoolIds));
            })
            ->count();
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
     * @param Collection<int, AcademicYear> $academicYears
     * @return array{labels: list<string>, values: list<int>}
     */
    private function calendarTypeChart(Collection $academicYears): array
    {
        $counts = $academicYears
            ->flatMap(fn (AcademicYear $academicYear): Collection => $academicYear->days)
            ->groupBy('type')
            ->map->count()
            ->sortDesc();

        return [
            'labels' => $counts->keys()
                ->map(fn (string $type): string => CalendarDay::TYPE_LABELS[$type] ?? $type)
                ->values()
                ->all(),
            'values' => $counts->values()->all(),
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
            ->get()
            ->sortBy(fn (Person $person): int => (int) $person->birth_date?->format('d'))
            ->values();
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, Announcement>
     */
    private function announcements(?array $schoolIds): Collection
    {
        return Announcement::query()
            ->with('school')
            ->visibleNow()
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->where(fn (Builder $query) => $query->whereNull('school_id')->orWhereIn('school_id', $schoolIds));
            })
            ->orderByDesc('highlight')
            ->latest('starts_at')
            ->limit(6)
            ->get();
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, AcademicYear>
     */
    private function monthAcademicCalendars(?array $schoolIds): Collection
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return AcademicYear::query()
            ->with([
                'school',
                'periods',
                'days' => fn ($query) => $query
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->orderBy('date'),
            ])
            ->where('academic_years.active', true)
            ->whereDate('academic_years.starts_at', '<=', $monthEnd)
            ->whereDate('academic_years.ends_at', '>=', $monthStart)
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('academic_years.school_id', $schoolIds))
            ->join('schools', 'academic_years.school_id', '=', 'schools.id')
            ->orderBy('schools.name')
            ->orderBy('academic_years.name')
            ->select('academic_years.*')
            ->get();
    }
}
