<?php

namespace App\Support;

use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherAccess
{
    /** @return Builder<SchoolClassComponent> */
    public static function assignments(User $user): Builder
    {
        $query = SchoolClassComponent::query()->where('school_class_components.active', true)
            ->whereHas('schoolClass', fn (Builder $class) => $class->operational()
                ->whereHas('academicYear', fn (Builder $year) => $year->where('active', true)->whereNotNull('approved_at')));

        if ($user->currentView() !== PersonSchoolRole::ROLE_TEACHER || ! $user->person_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $assignment) use ($user): void {
            $assignment->where('teacher_person_id', $user->person_id)
                ->orWhereHas('substitutions', fn (Builder $substitution) => $substitution
                    ->where('substitute_teacher_person_id', $user->person_id)
                    ->whereDate('starts_at', '<=', now()->toDateString())
                    ->where(fn (Builder $end) => $end->whereNull('ends_at')
                        ->orWhereDate('ends_at', '>=', now()->toDateString())));
        });
    }

    /** @return Collection<int, int> */
    public static function classIds(User $user): Collection
    {
        return self::assignments($user)->pluck('school_class_id')->unique()->values();
    }

    public static function canSeePerson(User $user, int $personId): bool
    {
        return $user->currentView() === PersonSchoolRole::ROLE_TEACHER
            && $user->person_id === $personId;
    }

    public static function schoolForTeacher(User $user): ?School
    {
        return School::query()->whereKey($user->visibleSchoolIds())->orderBy('name')->first();
    }
}
