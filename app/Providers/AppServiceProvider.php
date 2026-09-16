<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\DiaryAlert;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Builder;
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
        Event::listen(Login::class, function (): void {
            session()->forget('announcements_presented');
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
