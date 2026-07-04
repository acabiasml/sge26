<?php

namespace App\Models;

use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistoryComponent extends Model
{
    use HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'student_academic_history_id',
        'position',
        'formation',
        'knowledge_area',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'formation',
            'knowledge_area',
            'name',
        ];
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicHistory::class, 'student_academic_history_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryRecord::class);
    }
}
