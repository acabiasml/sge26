<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class PersonNameSearch
{
    public static function apply(Builder $query, string $term, string $prefix = ''): Builder
    {
        $words = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return $query;
        }

        return $query->where(function (Builder $names) use ($words, $prefix): void {
            foreach (['full_name', 'social_name'] as $column) {
                $names->orWhere(function (Builder $name) use ($words, $prefix, $column): void {
                    foreach ($words as $word) {
                        $name->where($prefix.$column, 'like', '%'.$word.'%');
                    }
                });
            }
        });
    }
}
