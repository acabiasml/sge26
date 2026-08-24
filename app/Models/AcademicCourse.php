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
        self::STAGE_TECHNICAL => 'Educação Profissional Técnica de Nível Médio',
        self::STAGE_OTHER => 'Outra etapa',
    ];

    public const MODALITY_REGULAR = 'regular';
    public const MODALITY_PROFESSIONAL_TECHNOLOGICAL = 'educacao_profissional_tecnologica';
    public const MODALITY_EJA = 'eja';
    public const MODALITY_SPECIAL = 'educacao_especial';
    public const MODALITY_INDIGENOUS = 'educacao_indigena';
    public const MODALITY_QUILOMBOLA = 'educacao_quilombola';
    public const MODALITY_RURAL = 'educacao_do_campo';
    public const MODALITY_DISTANCE = 'educacao_a_distancia';
    public const MODALITY_OTHER = 'outra';

    public const MODALITY_LABELS = [
        self::MODALITY_REGULAR => 'Regular',
        self::MODALITY_PROFESSIONAL_TECHNOLOGICAL => 'Educação Profissional e Tecnológica',
        self::MODALITY_EJA => 'Educação de Jovens e Adultos',
        self::MODALITY_SPECIAL => 'Educação Especial',
        self::MODALITY_INDIGENOUS => 'Educação Escolar Indígena',
        self::MODALITY_QUILOMBOLA => 'Educação Escolar Quilombola',
        self::MODALITY_RURAL => 'Educação do Campo',
        self::MODALITY_DISTANCE => 'Educação a Distância',
        self::MODALITY_OTHER => 'Outra modalidade',
    ];

    protected $fillable = [
        'academic_year_id',
        'starts_period_id',
        'ends_period_id',
        'name',
        'itinerary_name',
        'technical_legal_basis',
        'accreditation_act',
        'authorization_act',
        'regulatory_process',
        'regulatory_opinion',
        'technological_axis',
        'offer_forms',
        'official_gazette_reference',
        'authorization_starts_at',
        'authorization_ends_at',
        'module_certifications',
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
            'authorization_starts_at' => 'date',
            'authorization_ends_at' => 'date',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
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
                    CurriculumCatalog::areaLabelForComponent($this, $first->area),
                    CurriculumCatalog::areaLabelForComponent($this, $second->area),
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
                        ->groupBy(fn (CurriculumComponent $component): string => CurriculumCatalog::areaLabelForComponent($this, $component->area))
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

    public function modalityLabel(): string
    {
        return self::MODALITY_LABELS[$this->modality] ?? (string) $this->modality;
    }

    public function isItineraryMatrix(): bool
    {
        return $this->stage === self::STAGE_TECHNICAL
            || $this->modality === self::MODALITY_PROFESSIONAL_TECHNOLOGICAL;
    }

    public function technicalLegalBasis(): string
    {
        return $this->technical_legal_basis ?: 'Lei Federal nº 9.394/1996 (LDB), arts. 36-B a 42; Lei Federal nº 11.741/2008; Resolução CNE/CP nº 1/2021; Resolução CNE/CEB nº 2/2020 (CNCT).';
    }

    public function regulatoryReference(): ?string
    {
        if ($this->stage !== self::STAGE_TECHNICAL) {
            return null;
        }

        return collect([
            $this->technicalLegalBasis(),
            filled($this->accreditation_act) ? 'Credenciamento: '.$this->accreditation_act : null,
            filled($this->authorization_act) ? 'Autorização: '.$this->authorization_act : null,
            filled($this->regulatory_process) ? 'Processo: '.$this->regulatory_process : null,
            filled($this->regulatory_opinion) ? 'Parecer: '.$this->regulatory_opinion : null,
            filled($this->official_gazette_reference) ? 'Publicação: '.$this->official_gazette_reference : null,
        ])->filter()->join(' ');
    }

    public function moduleCertificationForPeriod(?int $position): ?string
    {
        if (! $position) {
            return null;
        }

        return collect(preg_split('/\R/u', (string) $this->module_certifications))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->get($position - 1);
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
