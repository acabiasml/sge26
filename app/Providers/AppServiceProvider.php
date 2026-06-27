<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('manage-schools', fn (User $user): bool => $user->canManageSchools());
        Gate::define('manage-school', fn (User $user, ?int $schoolId = null): bool => $user->canManageSchool($schoolId));
        Gate::define('manage-people', fn (User $user, ?int $schoolId = null): bool => $user->canManagePeople($schoolId));
        Gate::define('assign-roles', fn (User $user, ?int $schoolId = null): bool => $user->canAssignRoles($schoolId));

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                $view->with([
                    'topbarAnnouncements' => collect(),
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

            $view->with([
                'topbarAnnouncements' => $announcements,
                'topbarAlertCount' => $announcements->count(),
            ]);
        });
    }
}
