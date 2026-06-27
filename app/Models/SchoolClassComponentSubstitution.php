<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClassComponentSubstitution extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'school_class_component_id',
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

    public function classComponent(): BelongsTo
    {
        return $this->belongsTo(SchoolClassComponent::class, 'school_class_component_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'substitute_teacher_person_id');
    }
}
