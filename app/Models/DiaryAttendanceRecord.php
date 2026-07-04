<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiaryAttendanceRecord extends Model
{
    use Auditable;

    public const STATUS_PRESENT = 'presente';
    public const STATUS_ABSENT = 'falta';
    public const STATUS_EXCUSED = 'justificada';
    public const STATUS_PARTIAL = 'parcial';

    public const STATUS_LABELS = [
        self::STATUS_PRESENT => 'Presente',
        self::STATUS_ABSENT => 'Falta',
        self::STATUS_EXCUSED => 'Justificada',
        self::STATUS_PARTIAL => 'Presença parcial',
    ];

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'academic_period_id',
        'teacher_person_id',
        'updated_by_person_id',
        'class_date',
        'lesson_count',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'class_date' => 'date',
            'lesson_count' => 'integer',
            'legacy_metadata' => 'array',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'teacher_person_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by_person_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DiaryAttendanceEntry::class);
    }
}
