<?php

namespace App\Models;

use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistoryYear extends Model
{
    use HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'student_academic_history_id',
        'position',
        'label',
        'year',
        'stage',
        'modality',
        'grade_phase',
        'school_name',
        'city',
        'state',
        'country',
        'transcript_mode',
        'final_result',
        'workload_hours',
        'school_days',
        'attendance_label',
        'minimum_attendance_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'workload_hours' => 'decimal:2',
            'school_days' => 'integer',
            'minimum_attendance_percentage' => 'decimal:2',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'label',
            'stage',
            'modality',
            'grade_phase',
            'school_name',
            'city',
            'country',
            'final_result',
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
