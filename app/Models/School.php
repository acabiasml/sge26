<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class School extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'legacy_id',
        'legacy_source',
        'legacy_metadata',
        'name',
        'legal_name',
        'cnpj',
        'inep',
        'founded_at',
        'phone',
        'email',
        'website',
        'letterhead_text',
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

        return $current?->dependency_component_limit ?? $this->dependency_component_limit;
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
        return collect([
            $this->name,
            $this->legal_name,
            $this->cnpj,
            $this->inep,
            $this->founded_at,
            $this->phone,
            $this->email,
            $this->letterhead_text,
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
        ])->every(fn ($value): bool => filled($value));
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
            'letterhead_text' => 'texto institucional/autorizativo',
            'address' => 'endereço',
            'city' => 'cidade',
            'state' => 'UF',
            'postal_code' => 'CEP',
        ];

        return collect($fields)
            ->filter(fn (string $label, string $field): bool => blank($this->{$field}))
            ->values()
            ->all();
    }
}
