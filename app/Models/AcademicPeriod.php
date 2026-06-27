<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AcademicPeriod extends Model
{
    use Auditable, HasTitleCaseAttributes;

    public const RECOVERY_NONE = 'none';
    public const RECOVERY_WEIGHTED = 'weighted';
    public const RECOVERY_REPLACE_ASSESSMENT = 'replace_assessment';
    public const RECOVERY_REPLACE_LOWEST = 'replace_lowest';

    public const RECOVERY_MODE_LABELS = [
        self::RECOVERY_NONE => 'Sem recuperação',
        self::RECOVERY_WEIGHTED => 'Nota própria com peso na média',
        self::RECOVERY_REPLACE_ASSESSMENT => 'Substitui uma avaliação definida',
        self::RECOVERY_REPLACE_LOWEST => 'Substitui a menor nota do período',
    ];

    protected $fillable = [
        'academic_year_id',
        'name',
        'starts_at',
        'ends_at',
        'ignore_saturdays',
        'ignore_sundays',
        'position',
        'notes',
        'recovery_mode',
        'recovery_weight',
        'recovery_replaced_rule_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'ignore_saturdays' => 'boolean',
            'ignore_sundays' => 'boolean',
            'recovery_weight' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assessmentRules(): HasMany
    {
        return $this->hasMany(SchoolAssessmentRule::class, 'academic_period_id');
    }

    public function recoveryReplacedRule(): BelongsTo
    {
        return $this->belongsTo(SchoolAssessmentRule::class, 'recovery_replaced_rule_id');
    }

    public function diaryConsolidation(): HasOne
    {
        return $this->hasOne(AcademicPeriodDiaryConsolidation::class);
    }

    public function schoolDayCount(): int
    {
        return $this->academicYear
            ->days()
            ->whereBetween('date', [
                $this->starts_at->toDateString(),
                $this->ends_at->toDateString(),
            ])
            ->where('counts_as_school_day', true)
            ->count();
    }
}
