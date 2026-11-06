<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HistoryCurriculumComponent extends Model
{
    use Auditable, HasTitleCaseAttributes;

    protected $fillable = ['knowledge_area_id', 'education_stage', 'formation', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected function titleCaseAttributes(): array
    {
        return ['name'];
    }

    protected function titleCaseAttributesPreservingRomanNumerals(): array
    {
        return ['name'];
    }

    public function historyComponents(): BelongsToMany
    {
        return $this->belongsToMany(StudentAcademicHistoryComponent::class, 'history_catalog_usages');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArea::class, 'knowledge_area_id');
    }
}
