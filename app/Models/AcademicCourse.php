<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\CurriculumCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AcademicCourse extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    public const STAGE_ELEMENTARY = 'fundamental';
    public const STAGE_HIGH_SCHOOL = 'medio';
    public const STAGE_TECHNICAL = 'tecnico';
    public const STAGE_OTHER = 'outro';

    public const STAGE_LABELS = [
        self::STAGE_ELEMENTARY => 'Ensino Fundamental',
        self::STAGE_HIGH_SCHOOL => 'Ensino Médio',
        self::STAGE_TECHNICAL => 'Ensino Técnico',
        self::STAGE_OTHER => 'Outra etapa',
    ];

    protected $fillable = [
        'academic_year_id',
        'starts_period_id',
        'ends_period_id',
        'name',
        'stage',
        'modality',
        'status',
        'workload_hours',
        'class_hour_minutes',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'legacy_metadata' => 'array',
            'active' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
            'modality',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function startsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'starts_period_id');
    }

    public function endsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'ends_period_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(CurriculumComponent::class)->orderBy('name');
    }

    public function componentsGroupedByArea(): Collection
    {
        $components = $this->relationLoaded('components')
            ? $this->components
            : $this->components()->with('area')->get();

        return $components
            ->sort(function (CurriculumComponent $first, CurriculumComponent $second): int {
                $areaComparison = strnatcasecmp(
                    $first->area?->name ?? 'Área não definida',
                    $second->area?->name ?? 'Área não definida',
                );

                if ($areaComparison !== 0) {
                    return $areaComparison;
                }

                return strnatcasecmp($first->name, $second->name);
            })
            ->values()
            ->groupBy(fn (CurriculumComponent $component): string => $component->area?->name ?? 'Área não definida')
            ->map(fn (Collection $components, string $area): array => [
                'area' => $area,
                'components' => $components->values(),
            ])
            ->values();
    }

    public function componentsGroupedByFormationAndArea(): Collection
    {
        $components = $this->relationLoaded('components')
            ? $this->components
            : $this->components()->with('area')->get();

        return $components
            ->sort(function (CurriculumComponent $first, CurriculumComponent $second): int {
                $firstFormation = CurriculumCatalog::formationLabelForArea($this, $first->area);
                $secondFormation = CurriculumCatalog::formationLabelForArea($this, $second->area);

                $formationComparison = CurriculumCatalog::formationOrder($firstFormation) <=> CurriculumCatalog::formationOrder($secondFormation);

                if ($formationComparison !== 0) {
                    return $formationComparison;
                }

                $areaComparison = strnatcasecmp(
                    $first->area?->name ?? 'Área não definida',
                    $second->area?->name ?? 'Área não definida',
                );

                if ($areaComparison !== 0) {
                    return $areaComparison;
                }

                return strnatcasecmp($first->name, $second->name);
            })
            ->values()
            ->groupBy(fn (CurriculumComponent $component): string => CurriculumCatalog::formationLabelForArea($this, $component->area))
            ->map(function (Collection $formationComponents, string $formation): array {
                return [
                    'formation' => $formation,
                    'rowspan' => $formationComponents->count(),
                    'areas' => $formationComponents
                        ->groupBy(fn (CurriculumComponent $component): string => $component->area?->name ?? 'Área não definida')
                        ->map(fn (Collection $areaComponents, string $area): array => [
                            'area' => $area,
                            'rowspan' => $areaComponents->count(),
                            'components' => $areaComponents->values(),
                        ])
                        ->values(),
                ];
            })
            ->values();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'academic_course_school_class')->withTimestamps();
    }

    public function enrollments(): BelongsToMany
    {
        return $this->belongsToMany(StudentEnrollment::class, 'academic_course_student_enrollment')->withTimestamps();
    }

    public function stageLabel(): string
    {
        return self::STAGE_LABELS[$this->stage] ?? $this->stage;
    }

    public function calculatedWorkloadHours(): float
    {
        $components = $this->relationLoaded('components')
            ? $this->components
            : $this->components()->get();

        return round($components->sum(fn (CurriculumComponent $component): float => $component->calculatedWorkloadHours($this)), 2);
    }

    public function formattedCalculatedWorkloadHours(): string
    {
        return number_format($this->calculatedWorkloadHours(), 2, ',', '.');
    }

    public function refreshWorkloadHours(): void
    {
        $this->forceFill([
            'workload_hours' => (int) round($this->calculatedWorkloadHours()),
        ])->save();
    }

    public function hasMatrixComponents(): bool
    {
        if ($this->relationLoaded('components')) {
            return $this->components->where('active', true)->isNotEmpty();
        }

        return $this->components()->where('active', true)->exists();
    }
}
