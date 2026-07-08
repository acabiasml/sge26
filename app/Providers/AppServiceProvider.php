<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\DiaryAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $appDir = trim((string) config('app.dir'), '/');

        if ($appDir === '') {
            $appDir = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        }

        if ($appDir !== '') {
            Livewire::setUpdateRoute(fn ($handle) => Route::post($appDir.'/livewire/update', $handle)
                ->middleware('web')
                ->name('app.livewire.update'));

            Livewire::setScriptRoute(fn ($handle) => Route::get($appDir.'/livewire/livewire.js', $handle));
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

            $diaryAlerts = DiaryAlert::query()
                ->with(['fromPerson', 'schoolClass', 'component', 'period'])
                ->where('to_person_id', $user->person_id)
                ->whereNull('resolved_at')
                ->whereNull('dismissed_at')
                ->latest()
                ->limit(5)
                ->get();

            $view->with([
                'topbarAnnouncements' => $announcements,
                'topbarDiaryAlerts' => $diaryAlerts,
                'topbarAlertCount' => $announcements->count() + $diaryAlerts->count(),
            ]);
        });
    }
}
