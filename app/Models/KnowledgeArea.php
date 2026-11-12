<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\HistoryCurriculumPropagation;
use App\Support\MatrixHistoryCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KnowledgeArea extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected static function booted(): void
    {
        static::updated(function (self $model): void {
            if ($model->wasChanged(['name', 'formation'])) {
                foreach ($model->components()->get() as $component) {
                    app(MatrixHistoryCatalog::class)->syncComponent($component, true);
                }
                foreach ($model->historyCatalogComponents()->with('area')->get() as $entry) {
                    app(HistoryCurriculumPropagation::class)->catalogChanged($entry);
                }
            }
        });
    }

    protected $fillable = [
        'formation',
        'name',
        'subtitle',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'matrix_uses_count' => 'integer',
            'history_uses_count' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function scopeWithUsageCounts(Builder $query): Builder
    {
        return $query->withCount(['components', 'historyCatalogComponents'])->addSelect([
            'matrix_uses_count' => DB::table('curriculum_components as matrix')->selectRaw('count(*)')
                ->where(function ($matrices): void {
                    $matrices->whereColumn('matrix.knowledge_area_id', 'knowledge_areas.id')
                        ->orWhereExists(function ($links): void {
                            $links->selectRaw('1')->from('matrix_catalog_components as links')
                                ->join('history_curriculum_components as catalog', 'catalog.id', '=', 'links.history_curriculum_component_id')
                                ->whereColumn('links.curriculum_component_id', 'matrix.id')
                                ->whereColumn('catalog.knowledge_area_id', 'knowledge_areas.id');
                        });
                }),
            'history_uses_count' => DB::table('history_catalog_usages as usages')
                ->join('history_curriculum_components as catalog', 'catalog.id', '=', 'usages.history_curriculum_component_id')
                ->whereColumn('catalog.knowledge_area_id', 'knowledge_areas.id')
                ->selectRaw('count(distinct usages.student_academic_history_component_id)'),
        ]);
    }

    public function usageCount(): int
    {
        return (int) $this->matrix_uses_count + (int) $this->history_uses_count;
    }

    public function selectionLabel(): string
    {
        return $this->name.(filled($this->subtitle) ? ' — '.$this->subtitle : '');
    }

    public static function withSubtitlesByName(): Collection
    {
        return static::query()->whereNotNull('subtitle')->get()
            ->keyBy(fn (self $area): string => mb_strtolower(trim($area->name)));
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
