<?php

namespace App\Models;

use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistoryComponent extends Model
{
    use HasFactory, HasTitleCaseAttributes;

    private bool $adoptingCatalogDefinition = false;

    protected $fillable = [
        'student_academic_history_id',
        'position',
        'formation',
        'module_label',
        'regulatory_reference',
        'knowledge_area',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        if ($this->adoptingCatalogDefinition) {
            return [];
        }

        return [
            'formation',
            'knowledge_area',
        ];
    }

    public function adoptCatalogDefinition(HistoryCurriculumComponent $entry): void
    {
        $entry->loadMissing('area');
        // Preserva a grafia da matriz, inclusive abreviações e nomes das áreas.
        $this->adoptLabels(['name' => $entry->name, 'formation' => $entry->formation, 'knowledge_area' => $entry->area->name]);
    }

    public function adoptLabels(array $labels): void
    {
        $this->setRawAttributes(array_merge($this->getAttributes(), $labels));
        $this->adoptingCatalogDefinition = true;
        try {
            $this->save();
        } finally {
            $this->adoptingCatalogDefinition = false;
        }
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicHistory::class, 'student_academic_history_id');
    }

    public function catalogEntries(): BelongsToMany
    {
        return $this->belongsToMany(HistoryCurriculumComponent::class, 'history_catalog_usages');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryRecord::class);
    }
}
