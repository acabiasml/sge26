<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use Auditable, HasTitleCaseAttributes;

    protected $fillable = [
        'school_id',
        'created_by_user_id',
        'title',
        'body',
        'starts_at',
        'ends_at',
        'highlight',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'highlight' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
