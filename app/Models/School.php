<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\OfficialDocumentCompliance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class School extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    public const REGISTRY_MEDIA = ['digital' => 'Livro digital', 'physical' => 'Livro físico'];

    public const DOCUMENT_AUTHORIZATIONS = [
        'accreditation' => 'Credenciamento da escola',
        AcademicCourse::STAGE_ELEMENTARY => 'Autorização do Ensino Fundamental',
        AcademicCourse::STAGE_HIGH_SCHOOL => 'Autorização do Ensino Médio',
    ];

    protected $fillable = [
        'legacy_id',
        'legacy_source',
        'legacy_metadata',
        'name',
        'legal_name',
        'maintainer',
        'creation_act',
        'accreditation_act',
        'education_system',
        'registry_medium',
        'document_authorizations',
        'cnpj',
        'inep',
        'founded_at',
        'phone',
        'email',
        'website',
        'letterhead_text',
        'letterhead_text_elementary',
        'letterhead_text_high_school',
        'logo_path',
        'address',
        'district',
        'number',
        'city',
        'state',
        'postal_code',
        'active',
        'dependency_component_limit',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'dependency_component_limit' => 'integer',
            'founded_at' => 'date',
            'legacy_metadata' => 'array',
            'document_authorizations' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
            'legal_name',
            'address',
            'district',
            'city',
        ];
    }

    public static function documentAuthorizationLabels(): array
    {
        $labels = self::DOCUMENT_AUTHORIZATIONS;
        foreach ([AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL, AcademicCourse::STAGE_TECHNICAL] as $stage) {
            foreach (AcademicCourse::MODALITY_LABELS as $modality => $label) {
                $labels[$stage.'_'.$modality] = AcademicCourse::STAGE_LABELS[$stage].' · '.$label;
            }
        }

        return $labels;
    }

    public static function supportsDocumentAuthorizationRenewal(string $kind): bool
    {
        return Str::before($kind, '_') === AcademicCourse::STAGE_ELEMENTARY
            || Str::before($kind, '_') === AcademicCourse::STAGE_HIGH_SCHOOL;
    }

    public function documentAddress(): string
    {
        $address = collect([$this->address, $this->number, $this->district,
            collect([$this->city, $this->state])->filter()->join('-'), $this->postal_code ? 'CEP '.$this->postal_code : null])->filter()->join(', ');

        return $address ? $address.'.' : '';
    }

    public function usesPhysicalRegistry(): bool
    {
        return $this->registry_medium === 'physical';
    }

    public function stateHeading(): ?string
    {
        $names = ['AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
            'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'];

        return isset($names[$this->state]) ? mb_strtoupper(($this->state === 'DF' ? '' : 'Estado de ').$names[$this->state]) : null;
    }

    public function technicalPrograms(): HasMany
    {
        return $this->hasMany(TechnicalProgram::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function assessmentRules(): HasMany
    {
        return $this->hasMany(SchoolAssessmentRule::class);
    }

    public function academicCriteria(): HasMany
    {
        return $this->hasMany(SchoolAcademicCriteria::class)->orderByDesc('effective_from');
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(SchoolConcept::class)->orderByDesc('effective_from')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return Collection<int, SchoolConcept>
     */
    public function conceptsForDate(Carbon|string|null $date = null): Collection
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $concepts = $this->relationLoaded('concepts') ? $this->concepts : $this->concepts()->get();
        $eligible = $concepts->filter(
            fn (SchoolConcept $concept): bool => $concept->effective_from !== null
                && $concept->effective_from->toDateString() <= $date
        );

        if ($eligible->isEmpty()) {
            $firstEffectiveFrom = $concepts->min(
                fn (SchoolConcept $concept): ?string => $concept->effective_from?->toDateString()
            );
            $eligible = $concepts->filter(
                fn (SchoolConcept $concept): bool => $concept->effective_from?->toDateString() === $firstEffectiveFrom
            );
        }

        return $eligible
            ->sortByDesc(fn (SchoolConcept $concept): string => $concept->effective_from->toDateString())
            ->unique(fn (SchoolConcept $concept): string => Str::lower(trim($concept->name)))
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values();
    }

    public function conceptForScore(float $score, Carbon|string|null $date = null): ?SchoolConcept
    {
        return $this->conceptsForDate($date)
            ->first(fn (SchoolConcept $concept): bool => $concept->matches($score));
    }

    public function dependencyComponentLimitForDate(Carbon|string|null $date = null): ?int
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $criteria = $this->relationLoaded('academicCriteria') ? $this->academicCriteria : $this->academicCriteria()->get();
        $current = $criteria
            ->filter(fn (SchoolAcademicCriteria $item): bool => $item->effective_from->toDateString() <= $date)
            ->sortByDesc(fn (SchoolAcademicCriteria $item): string => $item->effective_from->toDateString())
            ->first();

        return $current ? $current->dependency_component_limit : $this->dependency_component_limit;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    public function formattedCnpj(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->cnpj);

        if (strlen($digits) !== 14) {
            return $this->cnpj ?: '-';
        }

        return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3)
            .'/'.substr($digits, 8, 4).'-'.substr($digits, 12, 2);
    }

    public function hasRequiredLetterheadForOfficialDocuments(): bool
    {
        return $this->missingOfficialDocumentFields() === [];
    }

    public function usesAutomaticLetterhead(): bool
    {
        return blank($this->letterhead_text) && blank($this->letterhead_text_elementary) && blank($this->letterhead_text_high_school);
    }

    public function documentAccreditationReference(): ?string
    {
        $act = $this->document_authorizations['accreditation'] ?? [];

        return OfficialDocumentCompliance::isCurrentAct($act, now('America/Cuiaba')->toDateString())
            ? $act['act'] : $this->accreditation_act;
    }

    /** @return list<string> */
    public function letterheadTextsForStages(array $stages = [], ?array $currentActs = null): array
    {
        if ($this->usesAutomaticLetterhead()) {
            $currentActs ??= OfficialDocumentCompliance::authorizationDetails($this, $stages)['acts'];

            return array_values(array_unique(array_column($currentActs, 'act')));
        }

        $fields = [
            AcademicCourse::STAGE_ELEMENTARY => 'letterhead_text_elementary',
            AcademicCourse::STAGE_HIGH_SCHOOL => 'letterhead_text_high_school',
        ];
        $texts = collect($fields)->only($stages)->map(fn (string $field): ?string => filled($this->{$field}) ? trim($this->{$field}) : $this->letterhead_text
        );
        if ($texts->isEmpty()) {
            return filled($this->letterhead_text) ? [$this->letterhead_text] : [];
        }
        if ($texts->unique()->count() <= 1) {
            return $texts->filter(fn ($text): bool => filled($text))->unique()->values()->all();
        }

        return $texts->filter(fn ($text): bool => filled($text))->map(
            fn (string $text, string $stage): string => AcademicCourse::STAGE_LABELS[$stage].': '.$text
        )->values()->all();
    }

    /**
     * @return list<string>
     */
    public function missingOfficialDocumentFields(): array
    {
        $fields = [
            'name' => 'nome da escola',
            'legal_name' => 'razão social',
            'cnpj' => 'CNPJ',
            'inep' => 'código INEP',
            'founded_at' => 'data de fundação',
            'phone' => 'telefone',
            'email' => 'e-mail',
            'address' => 'endereço',
            'city' => 'cidade',
            'state' => 'UF',
            'postal_code' => 'CEP',
        ];

        $missing = collect($fields)
            ->filter(fn (string $label, string $field): bool => blank($this->{$field}))
            ->values()
            ->all();
        if ($this->letterheadTextsForStages() === []) {
            $missing[] = 'texto institucional/autorizativo';
        }

        return $missing;
    }
}
