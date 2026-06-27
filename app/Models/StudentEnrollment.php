<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentEnrollment extends Model
{
    use Auditable, HasFactory;

    public const STATUS_ENROLLED = 'matriculado';
    public const STATUS_TRANSFERRED = 'transferido';
    public const STATUS_RECLASSIFIED = 'reclassificado';
    public const STATUS_CANCELLED = 'cancelado';

    public const TYPE_REGULAR = 'regular';
    public const TYPE_LISTENER = 'ouvinte';

    public const STATUS_LABELS = [
        self::STATUS_ENROLLED => 'Matriculado',
        self::STATUS_TRANSFERRED => 'Transferido',
        self::STATUS_RECLASSIFIED => 'Reclassificado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const TYPE_LABELS = [
        self::TYPE_REGULAR => 'Regular',
        self::TYPE_LISTENER => 'Ouvinte',
    ];

    protected $fillable = [
        'school_class_id',
        'person_id',
        'enrolled_by_person_id',
        'transferred_by_person_id',
        'cancelled_by_person_id',
        'reclassified_from_enrollment_id',
        'reclassified_by_person_id',
        'enrolled_at',
        'transferred_at',
        'cancelled_at',
        'reclassified_at',
        'status',
        'type',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'transferred_at' => 'date',
            'cancelled_at' => 'date',
            'reclassified_at' => 'date',
            'legacy_metadata' => 'array',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'enrolled_by_person_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'transferred_by_person_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cancelled_by_person_id');
    }

    public function reclassifiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reclassified_from_enrollment_id');
    }

    public function reclassifiedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reclassified_by_person_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(AcademicCourse::class, 'academic_course_student_enrollment')->withTimestamps();
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(DiaryAssessmentResult::class);
    }

    public function attendanceEntries(): HasMany
    {
        return $this->hasMany(DiaryAttendanceEntry::class);
    }

    public function attendanceJustifications(): HasMany
    {
        return $this->hasMany(DiaryAttendanceJustification::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ENROLLED;
    }
}
