<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentHistoryDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }

    public function scopeForEditor(Builder $query, User $user, Person $person, ?StudentAcademicHistory $history): Builder
    {
        return $query->where('user_id', $user->id)->where('person_id', $person->id)
            ->where('editor_key', $history?->id ?? 0);
    }
}
