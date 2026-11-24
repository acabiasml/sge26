<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryAttendanceJustification extends Model
{
    use Auditable;

    public const EFFECTS = [
        'documented' => 'Somente justificativa',
        'excused' => 'Abono autorizado',
        'compensated' => 'Compensação cumprida',
    ];

    protected $fillable = [
        'student_enrollment_id',
        'starts_at',
        'ends_at',
        'reason',
        'attendance_effect',
        'attendance_basis',
        'granted_by_person_id',
        'updated_by_person_id',
        'modification_count',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'modification_count' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'granted_by_person_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by_person_id');
    }

    public function appliesTo(string $date): bool
    {
        return $this->attendance_effect !== 'documented'
            && $date >= $this->starts_at->toDateString() && $date <= $this->ends_at->toDateString();
    }
}
