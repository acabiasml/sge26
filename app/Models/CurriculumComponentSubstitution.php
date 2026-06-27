<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumComponentSubstitution extends Model
{
    use Auditable;

    protected $fillable = [
        'curriculum_component_id',
        'substitute_teacher_person_id',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CurriculumComponent::class, 'curriculum_component_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'substitute_teacher_person_id');
    }
}
