<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriodDiaryConsolidation extends Model
{
    use Auditable;

    protected $fillable = [
        'academic_period_id',
        'consolidated',
        'consolidated_at',
        'consolidated_by_person_id',
        'reopened_at',
        'reopened_by_person_id',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'consolidated' => 'boolean',
            'consolidated_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function consolidatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'consolidated_by_person_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reopened_by_person_id');
    }
}
