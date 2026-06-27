<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClassComponent extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'school_class_id',
        'curriculum_component_id',
        'teacher_person_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'teacher_person_id');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(SchoolClassComponentSubstitution::class);
    }
}
