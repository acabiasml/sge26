<?php

namespace App\Support;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Support\Carbon;

class SchoolSignatureStaff
{
    /** @return array<string, Person|null> */
    public static function forSchool(?School $school, mixed $date = null): array
    {
        $positions = [
            PersonSchoolRole::POSITION_DIRECTOR,
            PersonSchoolRole::POSITION_SECRETARY,
            PersonSchoolRole::POSITION_COORDINATOR,
        ];

        if (! $school) {
            return array_fill_keys($positions, null);
        }

        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $roles = PersonSchoolRole::query()
            ->with('person')
            ->where('school_id', $school->id)
            ->where('role', PersonSchoolRole::ROLE_MANAGER)
            ->whereIn('position', $positions)
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', $date))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date))
            ->whereHas('person', fn ($query) => $query->where('active', true))
            ->orderByRaw('CASE WHEN started_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return collect($positions)->mapWithKeys(fn (string $position): array => [
            $position => $roles->firstWhere('position', $position)?->person,
        ])->all();
    }
}
