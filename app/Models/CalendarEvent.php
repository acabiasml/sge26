<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use Auditable, HasTitleCaseAttributes;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'created_by_user_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'category',
        'highlight',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'highlight' => 'boolean',
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
