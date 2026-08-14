<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiaryAssessment extends Model
{
    protected $attributes = [
        'weight' => 10,
        'maximum_score' => 10,
    ];

    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'academic_period_id',
        'school_assessment_rule_id',
        'is_recovery',
        'recovery_mode',
        'recovery_replaced_rule_id',
        'teacher_person_id',
        'title',
        'weight',
        'maximum_score',
        'assessment_date',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'maximum_score' => 'decimal:2',
            'is_recovery' => 'boolean',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CurriculumComponent::class, 'curriculum_component_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SchoolAssessmentRule::class, 'school_assessment_rule_id');
    }

    public function recoveryReplacedRule(): BelongsTo
    {
        return $this->belongsTo(SchoolAssessmentRule::class, 'recovery_replaced_rule_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'teacher_person_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiaryAssessmentResult::class);
    }
}
