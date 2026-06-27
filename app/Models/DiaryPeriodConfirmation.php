<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryPeriodConfirmation extends Model
{
    use Auditable;

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'academic_period_id',
        'confirmed',
        'confirmed_at',
        'confirmed_by_person_id',
        'reopened_at',
        'reopened_by_person_id',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return ['confirmed' => 'boolean', 'confirmed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function confirmedBy(): BelongsTo { return $this->belongsTo(Person::class, 'confirmed_by_person_id'); }
    public function reopenedBy(): BelongsTo { return $this->belongsTo(Person::class, 'reopened_by_person_id'); }
}
