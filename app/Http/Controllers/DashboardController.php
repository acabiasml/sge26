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
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = now('America/Sao_Paulo');
        $birthdayWeekStartsAt = $today->copy()->startOfWeek(CarbonInterface::SUNDAY);
        $birthdayWeekEndsAt = $today->copy()->endOfWeek(CarbonInterface::SATURDAY);
        $manageableSchoolIds = $user->isAdministrator() ? null : $user->manageableSchoolIds();
        $visibleSchoolIds = $user->isAdministrator() ? null : $user->visibleSchoolIds();
        $calendarSchoolIds = $user->isAdministrator()
            ? null
            : ($user->isManager() ? $manageableSchoolIds : $visibleSchoolIds);
        $canManagePeople = $user->canManagePeople();

        $roleCounts = collect(PersonSchoolRole::ROLE_LABELS)
            ->only([
                PersonSchoolRole::ROLE_STUDENT,
                PersonSchoolRole::ROLE_TEACHER,
                PersonSchoolRole::ROLE_MANAGER,
                PersonSchoolRole::ROLE_EMPLOYEE,
            ])
            ->mapWithKeys(fn (string $label, string $role): array => [
                $role => $canManagePeople ? $this->activeRoleCount($role, $manageableSchoolIds) : 0,
            ])
            ->all();

        $birthdays = $this->birthdays($calendarSchoolIds, $today->month);
        $weekBirthdays = $this->birthdaysBetween(
            $calendarSchoolIds,
            $birthdayWeekStartsAt,
            $birthdayWeekEndsAt,
        );
        $monthAcademicCalendars = $this->monthAcademicCalendars($calendarSchoolIds);

        return view('dashboard', [
            'schoolCount' => $canManagePeople ? $this->schoolCount($manageableSchoolIds) : 0,
            'personCount' => $canManagePeople ? $this->personCount($manageableSchoolIds) : 0,
            'activeEnrollmentCount' => $canManagePeople ? $this->activeEnrollmentCount($manageableSchoolIds) : 0,
            'activeAcademicYearCount' => $canManagePeople ? $this->activeAcademicYearCount($calendarSchoolIds) : 0,
            'registrationPendingCount' => $canManagePeople ? $this->registrationPendingCount($manageableSchoolIds) : 0,
            'monthSchoolDayCount' => $monthAcademicCalendars->sum(fn (AcademicYear $academicYear): int => $academicYear->days->where('counts_as_school_day', true)->count()),
            'roleCounts' => $roleCounts,
            'roleChart' => $this->roleChart($roleCounts),
            'studentsBySchoolChart' => $canManagePeople ? $this->studentsBySchoolChart($manageableSchoolIds) : ['labels' => [], 'values' => []],
            'calendarTypeChart' => $canManagePeople ? $this->calendarTypeChart($monthAcademicCalendars) : ['labels' => [], 'values' => []],
            'birthdays' => $birthdays,
            'weekBirthdays' => $weekBirthdays,
            'birthdayWeekStartsAt' => $birthdayWeekStartsAt,
            'birthdayWeekEndsAt' => $birthdayWeekEndsAt,
            'announcements' => $this->announcements($visibleSchoolIds),
            'monthAcademicCalendars' => $monthAcademicCalendars,
            'combinedCalendarMonth' => AcademicCalendarGrid::combinedMonth($monthAcademicCalendars, $birthdays, $today),
        ]);
    }

    /**
     * @param  list<int>|null  $schoolIds
     */
    private function schoolCount(?array $schoolIds): int
    {
        return School::query()
            ->where('active', true)
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('id', $schoolIds))
            ->count();
    }

    /**
     * @param  list<int>|null  $schoolIds
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
     * @param  list<int>|null  $schoolIds
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
     * @param  list<int>|null  $schoolIds
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
     * @param  list<int>|null  $schoolIds
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
     * @param  list<int>|null  $schoolIds
     */
    private function registrationPendingCount(?array $schoolIds): int
    {
        return Person::query()
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('cpf')
                    ->orWhere('cpf', '')
                    ->orWhereNull('birth_date')
                    ->orWhereNull('mother_name')
                    ->orWhere('mother_name', '')
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
     * @param  array<string, int>  $roleCounts
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
     * @param  list<int>|null  $schoolIds
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
     * @param  Collection<int, AcademicYear>  $academicYears
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
     * @param  list<int>|null  $schoolIds
     * @return Collection<int, Person>
     */
    private function birthdays(?array $schoolIds, int $month): Collection
    {
        return $this->birthdayQuery($schoolIds)
            ->whereMonth('birth_date', $month)
            ->get()
            ->sortBy(fn (Person $person): int => (int) $person->birth_date?->format('d'))
            ->values();
    }

    /**
     * @param  list<int>|null  $schoolIds
     * @return Collection<int, Person>
     */
    private function birthdaysBetween(?array $schoolIds, CarbonInterface $startsAt, CarbonInterface $endsAt): Collection
    {
        $dateOrder = collect(range(0, (int) $startsAt->diffInDays($endsAt)))
            ->mapWithKeys(fn (int $offset): array => [
                $startsAt->copy()->addDays($offset)->format('m-d') => $offset,
            ]);
        $months = $dateOrder->keys()
            ->map(fn (string $date): int => (int) substr($date, 0, 2))
            ->unique()
            ->values();

        return $this->birthdayQuery($schoolIds)
            ->where(function (Builder $query) use ($months): void {
                $months->each(fn (int $month) => $query->orWhereMonth('birth_date', $month));
            })
            ->get()
            ->filter(fn (Person $person): bool => $dateOrder->has($person->birth_date?->format('m-d')))
            ->sortBy(fn (Person $person): int => $dateOrder->get($person->birth_date?->format('m-d')))
            ->values();
    }

    /**
     * @param  list<int>|null  $schoolIds
     * @return Builder<Person>
     */
    private function birthdayQuery(?array $schoolIds): Builder
    {
        return Person::query()
            ->where('active', true)
            ->whereNotNull('birth_date')
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles)->whereIn('school_id', $schoolIds));
            });
    }

    /**
     * @param  list<int>|null  $schoolIds
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
     * @param  list<int>|null  $schoolIds
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
                    ->whereDate('date', '>=', $monthStart)
                    ->whereDate('date', '<=', $monthEnd)
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
