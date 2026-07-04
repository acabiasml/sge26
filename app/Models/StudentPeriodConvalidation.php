<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPeriodConvalidation extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_enrollment_id',
        'academic_period_id',
        'curriculum_component_id',
        'convalidated_by_person_id',
        'score',
        'source_school',
        'notes',
        'convalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:1',
            'convalidated_at' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CurriculumComponent::class, 'curriculum_component_id');
    }

    public function convalidatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'convalidated_by_person_id');
    }
}
