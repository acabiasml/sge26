<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClassScheduleSlot extends Model
{
    use Auditable;

    public const TYPE_CLASS = 'aula';
    public const TYPE_BREAK = 'intervalo';

    public const TYPE_LABELS = [
        self::TYPE_CLASS => 'Aula',
        self::TYPE_BREAK => 'Intervalo',
    ];

    public const WEEKDAY_LABELS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
    ];

    protected $fillable = [
        'school_class_schedule_id',
        'weekday',
        'starts_at',
        'ends_at',
        'type',
        'school_class_component_id',
        'label',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SchoolClassSchedule::class, 'school_class_schedule_id');
    }

    public function componentAssignment(): BelongsTo
    {
        return $this->belongsTo(SchoolClassComponent::class, 'school_class_component_id');
    }
}
