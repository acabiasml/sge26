<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryExcludedDate extends Model
{
    use Auditable;

    protected $fillable = ['school_class_component_id', 'academic_period_id', 'class_date'];

    protected function casts(): array
    {
        return ['class_date' => 'date:Y-m-d'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchoolClassComponent::class, 'school_class_component_id');
    }

    protected function auditSchoolId(): ?int
    {
        return $this->assignment?->schoolClass?->academicYear?->school_id;
    }
}
