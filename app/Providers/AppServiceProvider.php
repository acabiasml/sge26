<?php

namespace App\Providers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CurriculumComponent;
use App\Models\DiaryAlert;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\ActiveRoleContext;
use App\Support\AuditLogGroupIndex;
use App\Support\ListingInitialData;
use App\Support\UserLastAccess;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();
        View::composer(ListingInitialData::views(), fn ($view) => ListingInitialData::capture($view));
        // Foreign-key cascades can also remove legacy diary context without model events.
        foreach ([User::class, Person::class, School::class, AcademicYear::class, AcademicCourse::class,
            SchoolClass::class, CurriculumComponent::class, StudentEnrollment::class,
            DiaryAssessment::class, DiaryAttendanceRecord::class] as $model) {
            $model::deleted(function (): void {
                ActiveRoleContext::forget();
                app(AuditLogGroupIndex::class)->invalidate();
            });
        }
        foreach ([DiaryAttendanceEntry::class => 'diary_attendance_record_id', DiaryAssessmentResult::class => 'diary_assessment_id'] as $model => $contextField) {
            $model::deleted(fn () => app(AuditLogGroupIndex::class)->invalidate());
            $model::updated(function ($record) use ($contextField): void {
                if ($record->wasChanged($contextField)) {
                    app(AuditLogGroupIndex::class)->invalidate();
                }
            });
        }

        Event::listen(Login::class, function (Login $event): void {
            session()->forget('announcements_presented');
            if ($event->user instanceof User) {
                app(UserLastAccess::class)->record($event->user);
            }
        });

        $appDir = trim((string) config('app.dir'), '/');

        if ($appDir === '') {
            $appDir = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        }

        if ($appDir !== '') {
            Livewire::setUpdateRoute(fn ($handle) => Route::post($appDir.'/livewire/update', $handle)
                ->middleware('web')
                ->name('app.livewire.update'));
        }

        Gate::define('manage-schools', fn (User $user): bool => $user->canManageSchools());
        Gate::define('manage-school', fn (User $user, ?int $schoolId = null): bool => $user->canManageSchool($schoolId));
        Gate::define('manage-people', fn (User $user, ?int $schoolId = null): bool => $user->canManagePeople($schoolId));
        Gate::define('assign-roles', fn (User $user, ?int $schoolId = null): bool => $user->canAssignRoles($schoolId));

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                $view->with([
                    'topbarAnnouncements' => collect(),
                    'topbarDiaryAlerts' => collect(),
                    'topbarAlertCount' => 0,
                ]);

                return;
            }

            $schoolIds = $user->isAdministrator() ? null : $user->visibleSchoolIds();

            $announcements = Announcement::query()
                ->with('school')
                ->visibleNow()
                ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                    $query->where(fn (Builder $query) => $query->whereNull('school_id')->orWhereIn('school_id', $schoolIds));
                })
                ->orderByDesc('highlight')
                ->latest('starts_at')
                ->limit(5)
                ->get();

            $shown = session('announcements_presented', []);
            $highlighted = Announcement::query()->with('school')->visibleTo($user)
                ->where('highlight', true)->whereNotIn('id', $shown)
                ->whereNotExists(function ($query) use ($user): void {
                    $query->selectRaw('1')->from('announcement_reads')
                        ->whereColumn('announcement_reads.announcement_id', 'announcements.id')
                        ->where('user_id', $user->id);
                })->latest('starts_at')->get();
            session(['announcements_presented' => array_values(array_unique(array_merge($shown, $highlighted->modelKeys())))]);
            $view->with('highlightedAnnouncements', $highlighted);

            $diaryAlerts = $user->hasActiveRole(PersonSchoolRole::ROLE_TEACHER) || $user->hasTeachingDiaries()
                ? DiaryAlert::query()
                    ->with(['fromPerson', 'schoolClass', 'component', 'period'])
                    ->where('to_person_id', $user->person_id)
                    ->whereNull('resolved_at')
                    ->whereNull('dismissed_at')
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect();

            $view->with([
                'topbarAnnouncements' => $announcements,
                'topbarDiaryAlerts' => $diaryAlerts,
                'topbarAlertCount' => $announcements->count() + $diaryAlerts->count(),
            ]);
        });
    }
}
