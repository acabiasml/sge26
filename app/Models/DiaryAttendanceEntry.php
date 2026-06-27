<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryAttendanceEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'diary_attendance_record_id',
        'student_enrollment_id',
        'status',
        'attended_lessons',
        'lesson_presence',
    ];

    protected function casts(): array
    {
        return [
            'attended_lessons' => 'integer',
            'lesson_presence' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(DiaryAttendanceRecord::class, 'diary_attendance_record_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
