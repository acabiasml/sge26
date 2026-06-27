<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolAssessmentRule extends Model
{
    use Auditable;

    protected $fillable = [
        'school_id',
        'academic_period_id',
        'name',
        'position',
        'weight',
        'maximum_score',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weight' => 'integer',
            'maximum_score' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DiaryAssessment::class);
    }

    public function label(): string
    {
        return $this->name ?: 'Avaliação '.$this->position;
    }
}
