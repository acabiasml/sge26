<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'academic_year_id',
        'starts_period_id',
        'ends_period_id',
        'name',
        'shift',
        'starts_at',
        'ends_at',
        'notes',
        'active',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'active' => 'boolean',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
            'shift',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function startsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'starts_period_id');
    }

    public function endsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'ends_period_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(AcademicCourse::class, 'academic_course_school_class')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function componentAssignments(): HasMany
    {
        return $this->hasMany(SchoolClassComponent::class);
    }

    public function diaryAssessments(): HasMany
    {
        return $this->hasMany(DiaryAssessment::class);
    }

    public function diaryAttendanceRecords(): HasMany
    {
        return $this->hasMany(DiaryAttendanceRecord::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SchoolClassSchedule::class);
    }
}
