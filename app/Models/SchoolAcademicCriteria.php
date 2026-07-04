<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAcademicCriteria extends Model
{
    use Auditable;

    protected $table = 'school_academic_criteria';

    protected $fillable = [
        'school_id',
        'effective_from',
        'dependency_component_limit',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'dependency_component_limit' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
