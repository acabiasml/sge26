<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryContent extends Model
{
    use Auditable;

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'academic_period_id',
        'class_date',
        'content',
        'created_by_person_id',
        'updated_by_person_id',
    ];

    protected function casts(): array
    {
        return ['class_date' => 'date'];
    }

    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class); }
    public function component(): BelongsTo { return $this->belongsTo(CurriculumComponent::class, 'curriculum_component_id'); }
    public function period(): BelongsTo { return $this->belongsTo(AcademicPeriod::class, 'academic_period_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Person::class, 'created_by_person_id'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(Person::class, 'updated_by_person_id'); }
}
