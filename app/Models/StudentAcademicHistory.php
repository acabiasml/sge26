<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistory extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'person_id',
        'school_id',
        'created_by_person_id',
        'updated_by_person_id',
        'title',
        'stage',
        'legal_basis',
        'notes',
        'issued_place',
        'issued_date',
        'active',
        'is_unified',
        'education_stage',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'active' => 'boolean',
            'is_unified' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
            'stage',
            'issued_place',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by_person_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryYear::class)->orderBy('position');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryComponent::class)->orderBy('position');
    }
}
