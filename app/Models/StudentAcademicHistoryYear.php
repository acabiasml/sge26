<?php

namespace App\Models;

use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\TextNormalizer;
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
        'source',
        'student_enrollment_id',
        'label',
        'year',
        'stage',
        'modality',
        'grade_phase',
        'grade_number',
        'imported_from_history_year_id',
        'school_name',
        'school_authorization',
        'source_document',
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
            'grade_number' => 'integer',
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

    public function getSchoolNameAttribute(?string $value): ?string
    {
        return TextNormalizer::schoolAcronym($value);
    }

    public function displaysCompletedWorkload(): bool
    {
        return $this->transcript_mode !== 'no_transcription'
            && ! in_array(mb_strtolower(trim((string) $this->final_result)), [
                'cursando', 'em andamento', 'em curso', 'matriculado', 'pendente', 'a cursar',
            ], true);
    }

    public function completedWorkloadHours(): ?float
    {
        if ($this->source !== 'system') {
            return $this->workload_hours !== null ? (float) $this->workload_hours : null;
        }

        $hours = $this->records->map(fn ($record) => $record->completedWorkloadHours($this))->filter(fn ($value) => $value !== null);

        return $hours->isEmpty() ? null : round($hours->sum(), 2);
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicHistory::class, 'student_academic_history_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryRecord::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
