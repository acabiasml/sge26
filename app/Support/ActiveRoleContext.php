<?php

namespace App\Support;

use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Reuse role reads only inside the current request, never across requests. */
final class ActiveRoleContext
{
    private const ATTRIBUTE = 'sge.active_roles';

    public static function for(User $user): Collection
    {
        $date = now()->toDateString();
        $key = $user->person_id.':'.$date;
        $request = app()->bound('request') ? request() : null;
        $roles = $request?->attributes->get(self::ATTRIBUTE, []) ?? [];
        if (array_key_exists($key, $roles)) {
            return $roles[$key];
        }

        $roles[$key] = PersonSchoolRole::query()->where('person_id', $user->person_id)
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', $date))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date))
            ->get();
        $request?->attributes->set(self::ATTRIBUTE, $roles);

        return $roles[$key];
    }

    public static function forget(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove(self::ATTRIBUTE);
        }
    }
}
