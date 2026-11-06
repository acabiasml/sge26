<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoryCurriculumAlias extends Model
{
    protected $fillable = ['history_curriculum_component_id', 'knowledge_area_id', 'education_stage', 'formation', 'name'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(HistoryCurriculumComponent::class, 'history_curriculum_component_id');
    }
}
