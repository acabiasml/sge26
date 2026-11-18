<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreeCourseOffering extends Model
{
    use Auditable;

    public const MODES = ['presencial' => 'Presencial', 'distancia' => 'A distância', 'hibrido' => 'Híbrido'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    protected function auditSchoolId(): ?int
    {
        return $this->freeCourse?->school_id;
    }

    public function freeCourse(): BelongsTo
    {
        return $this->belongsTo(FreeCourse::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FreeCourseParticipant::class);
    }
}
