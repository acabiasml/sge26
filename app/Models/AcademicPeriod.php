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

    public const RECOVERY_REPLACE_PERIOD_AVERAGE = 'replace_period_average';

    public const RECOVERY_MODE_LABELS = [
        self::RECOVERY_NONE => 'Sem recuperação',
        self::RECOVERY_WEIGHTED => 'Nota própria com peso na média',
        self::RECOVERY_REPLACE_ASSESSMENT => 'Substitui uma avaliação definida',
        self::RECOVERY_REPLACE_LOWEST => 'Substitui a menor nota do período',
        self::RECOVERY_REPLACE_PERIOD_AVERAGE => 'Substitui a média do período quando for maior',
    ];

    protected $fillable = [
        'academic_year_id',
        'name',
        'starts_at',
        'ends_at',
        'ignore_saturdays',
        'ignore_sundays',
        'allow_diary_entries_outside_period',
        'position',
        'notes',
        'recovery_mode',
        'recovery_weight',
        'recovery_replaced_rule_id',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'ignore_saturdays' => 'boolean',
            'ignore_sundays' => 'boolean',
            'allow_diary_entries_outside_period' => 'boolean',
            'recovery_weight' => 'integer',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    protected function titleCaseAttributesPreservingRomanNumerals(): array
    {
        return [
            'name',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (AcademicPeriod $period): void {
            $schoolId = $period->academicYear()->value('school_id');

            if (! $schoolId) {
                return;
            }

            $period->assessmentRules()->firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'position' => 1,
                ],
                [
                    'name' => 'Avaliação 1',
                    'weight' => 10,
                    'maximum_score' => 10,
                ],
            );
        });
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

    public function behaviorGrades(): HasMany
    {
        return $this->hasMany(StudentBehaviorGrade::class);
    }

    public function schoolDayCount(): int
    {
        return $this->academicYear
            ->days()
            ->whereDate('date', '>=', $this->starts_at->toDateString())
            ->whereDate('date', '<=', $this->ends_at->toDateString())
            ->where('counts_as_school_day', true)
            ->count();
    }
}
