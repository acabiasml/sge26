<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAcademicHistoryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_academic_history_component_id',
        'student_academic_history_year_id',
        'score_label',
        'score_numeric',
        'workload_hours',
        'completed_workload_hours',
        'frequency_label',
        'frequency_percentage',
        'absences',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'score_numeric' => 'decimal:2',
            'workload_hours' => 'decimal:2',
            'completed_workload_hours' => 'decimal:2',
            'frequency_percentage' => 'decimal:2',
            'absences' => 'integer',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicHistoryComponent::class, 'student_academic_history_component_id');
    }

    public function completedWorkloadHours(?StudentAcademicHistoryYear $year = null): ?float
    {
        $year ??= $this->year;

        return $year?->source === 'system'
            ? ($this->completed_workload_hours !== null ? (float) $this->completed_workload_hours : null)
            : ($this->workload_hours !== null ? (float) $this->workload_hours : null);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicHistoryYear::class, 'student_academic_history_year_id');
    }
}
