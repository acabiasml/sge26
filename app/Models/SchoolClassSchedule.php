<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClassSchedule extends Model
{
    use Auditable, HasTitleCaseAttributes;

    protected $fillable = ['school_class_id', 'name', 'starts_at', 'ends_at', 'notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    protected function titleCaseAttributes(): array { return ['name']; }
    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class); }
    public function slots(): HasMany { return $this->hasMany(SchoolClassScheduleSlot::class); }
}
