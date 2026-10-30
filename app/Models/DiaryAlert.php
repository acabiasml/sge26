<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryAlert extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'academic_period_id',
        'from_person_id',
        'to_person_id',
        'message',
        'resolved_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function canBeManagedBy(User $user): bool
    {
        $schoolId = $this->schoolClass?->academicYear?->school_id;

        return $schoolId !== null && $user->person_id !== null
            && (int) $this->from_person_id === (int) $user->person_id
            && $user->canManageSchool($schoolId);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CurriculumComponent::class, 'curriculum_component_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function fromPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'from_person_id');
    }

    public function toPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'to_person_id');
    }
}
