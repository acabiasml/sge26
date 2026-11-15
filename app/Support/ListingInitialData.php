<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** Avoid fetching a listing twice while rendering its initial HTML response. */
final class ListingInitialData
{
    private const VIEWS = [
        'histories' => ['student-histories.index'],
        'school-years' => ['schools.academic-years.index'],
        'knowledge-areas' => ['knowledge-areas.index'],
        'area-usages' => ['knowledge-areas.usages'],
        'justifications' => ['attendance-justifications.index'],
        'diaries' => ['teacher-diaries.index', 'teacher-diaries.management-index'],
        'quality' => ['data-quality.index'],
        'enrollment-overview' => ['student-enrollments.overview'],
        'enrollments' => ['student-enrollments.index'],
        'announcements' => ['announcements.index'],
        'student-diaries' => ['student-diaries.index'],
        'teacher-schedules' => ['teacher-schedules.index'],
    ];

    public static function views(): array
    {
        return array_merge(...array_values(self::VIEWS));
    }

    public static function capture(View $view): void
    {
        request()->attributes->set('sge.initial_listing.'.$view->name(), [
            'data' => $view->getData(),
            'user' => auth()->id(),
            'role' => auth()->user()?->currentView(),
        ]);
    }

    public static function get(string $screen, Request $listingRequest, array $allowed, array $context): ?View
    {
        // Nested URL filters are normalized by Livewire, not by the initial controller.
        if (request()->has('f')) {
            return null;
        }
        $initialQuery = array_filter(request()->only($allowed), fn ($value) => $value !== '' && $value !== null);
        if ($initialQuery != $listingRequest->query()) {
            return null;
        }

        foreach (self::VIEWS[$screen] ?? [] as $name) {
            $initial = request()->attributes->get('sge.initial_listing.'.$name);
            if (! $initial || $initial['user'] !== auth()->id() || $initial['role'] !== auth()->user()?->currentView()) {
                continue;
            }
            foreach (['school', 'area', 'class'] as $key) {
                if (isset($context[$key]) && (int) (($initial['data'][$key] ?? null)?->id ?? 0) !== (int) $context[$key]) {
                    continue 2;
                }
            }

            return view($name, $initial['data']);
        }

        return null;
    }
}
