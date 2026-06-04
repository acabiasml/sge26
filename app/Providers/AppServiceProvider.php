<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
    }
}
