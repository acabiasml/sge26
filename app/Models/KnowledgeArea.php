<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\MatrixHistoryCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeArea extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected static function booted(): void
    {
        static::updated(function (self $model): void {
            if ($model->wasChanged(['name', 'formation'])) {
                foreach ($model->components()->get() as $component) {
                    app(MatrixHistoryCatalog::class)->syncComponent($component);
                }
            }
        });
    }

    protected $fillable = [
        'formation',
        'name',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function historyCatalogComponents(): HasMany
    {
        return $this->hasMany(HistoryCurriculumComponent::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(CurriculumComponent::class);
    }
}
