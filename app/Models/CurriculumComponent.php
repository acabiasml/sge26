<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumComponent extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'academic_course_id',
        'knowledge_area_id',
        'starts_period_id',
        'ends_period_id',
        'name',
        'weekly_lessons',
        'workload_hours',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'weekly_lessons' => 'integer',
            'legacy_metadata' => 'array',
            'active' => 'boolean',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademicCourse::class, 'academic_course_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArea::class, 'knowledge_area_id');
    }

    public function startsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'starts_period_id');
    }

    public function endsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'ends_period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DiaryAssessment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(DiaryAttendanceRecord::class);
    }

    public function calculatedWorkloadHours(?AcademicCourse $course = null): float
    {
        $course ??= $this->relationLoaded('course') ? $this->course : $this->course()->first();

        if (! $course || $this->weekly_lessons === null) {
            return 0.0;
        }

        return round(((int) $this->weekly_lessons * (int) $course->class_hour_minutes * 40) / 60, 2);
    }

    public function formattedCalculatedWorkloadHours(?AcademicCourse $course = null): string
    {
        return number_format($this->calculatedWorkloadHours($course), 2, ',', '.');
    }

    public function isActiveInPeriod(AcademicPeriod $period): bool
    {
        $this->loadMissing('course.startsPeriod', 'course.endsPeriod', 'startsPeriod', 'endsPeriod');

        $starts = $this->startsPeriod ?? $this->course?->startsPeriod;
        $ends = $this->endsPeriod ?? $this->course?->endsPeriod;

        if ($starts && $period->position < $starts->position) {
            return false;
        }

        if ($ends && $period->position > $ends->position) {
            return false;
        }

        return true;
    }
}
