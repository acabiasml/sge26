<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'school_id',
        'name',
        'reference_year',
        'starts_at',
        'ends_at',
        'approved_at',
        'class_hour_minutes',
        'minimum_school_days',
        'passing_points',
        'minimum_attendance_percentage',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'approved_at' => 'date',
            'passing_points' => 'decimal:1',
            'minimum_attendance_percentage' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AcademicYear $academicYear): void {
            $academicYear->passing_points ??= 24;
            $academicYear->minimum_attendance_percentage ??= 75;
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(CalendarDay::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(AcademicCourse::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function schoolDayCount(): int
    {
        return $this->days()->where('counts_as_school_day', true)->count();
    }
}
