<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeCourseParticipant extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'date', 'removed_at' => 'datetime'];
    }

    protected function auditSchoolId(): ?int
    {
        return $this->offering?->freeCourse?->school_id;
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(FreeCourseOffering::class, 'free_course_offering_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
