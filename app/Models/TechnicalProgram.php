<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TechnicalProgram extends Model
{
    use Auditable;

    public const OFFER_MODES = ['concomitante' => 'Concomitante', 'subsequente' => 'Subsequente', 'integrado' => 'Integrado'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'offer_modes' => 'array', 'active' => 'boolean', 'authorization_starts_at' => 'date', 'authorization_ends_at' => 'date'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArea::class, 'knowledge_area_id');
    }

    public function getModulesAttribute($value): array
    {
        $modules = is_array($value) ? $value : json_decode($value ?: '[]', true);
        if (! $this->knowledge_area_id) {
            return $modules;
        }
        $ids = collect($modules)->flatMap(fn ($m) => collect($m['components'])->pluck('catalog_component_id'))->filter()->unique();
        $entries = HistoryCurriculumComponent::whereIn('id', $ids)->where('knowledge_area_id', $this->knowledge_area_id)->get()->keyBy('id');
        foreach ($modules as &$module) {
            foreach ($module['components'] as &$component) {
                if ($entry = $entries->get($component['catalog_component_id'] ?? null)) {
                    $component['name'] = $entry->name;
                }
            }
        }
        unset($module, $component);

        return $modules;
    }

    public function getOfferFormsAttribute($value): string
    {
        return collect($this->offer_modes ?? [])->map(fn ($mode) => self::OFFER_MODES[$mode] ?? '')->filter()->join(', ', ' e ');
    }

    public function getMaintainerAttribute($value)
    {
        return $this->school?->maintainer;
    }

    public function getCreationActAttribute($value)
    {
        return $this->school?->creation_act;
    }

    public function getAccreditationActAttribute($value)
    {
        return $this->school?->accreditation_act;
    }

    public function getEducationSystemAttribute($value)
    {
        return $this->school?->education_system;
    }

    public function getStateHeadingAttribute($value)
    {
        return $this->school?->stateHeading();
    }

    public function getFrontAddressAttribute($value)
    {
        return $this->school?->documentAddress();
    }

    public function getBackAddressAttribute($value)
    {
        return $this->school?->documentAddress().($this->school?->phone ? ' Tel.: '.$this->school->phone.'.' : '');
    }

    public function authorizationReference(): ?string
    {
        $act = filled($this->authorization_act) ? trim(preg_split('/\s+[—–]\s+/u', $this->authorization_act, 2)[0]) : null;

        return collect([$act, $this->official_gazette_reference])->filter()->unique()->join(' · ') ?: null;
    }

    public function documentData(): array
    {
        return $this->only(['name', 'graduate_title', 'technological_axis', 'education_mode', 'offer_forms', 'maintainer', 'state_heading',
            'education_system', 'creation_act', 'accreditation_act', 'authorization_act', 'legal_basis', 'registry_resolution',
            'front_address', 'back_address', 'modules', 'official_gazette_reference']);
    }

    public function matrices(): HasMany
    {
        return $this->hasMany(AcademicCourse::class);
    }

    public function componentDefinitions(): Collection
    {
        return collect($this->modules)->flatMap(fn ($module) => collect($module['components'])
            ->map(fn ($component) => $component + ['module_key' => $module['key'], 'module_name' => $module['name']]))->keyBy('key');
    }

    public function totalHours(): float
    {
        return round($this->componentDefinitions()->sum('hours'), 2);
    }

    public function missingDocumentFields(): array
    {
        $labels = ['name' => 'nome do curso', 'graduate_title' => 'título conferido', 'technological_axis' => 'eixo tecnológico',
            'offer_forms' => 'formas de oferta', 'education_mode' => 'modalidade', 'maintainer' => 'mantenedora',
            'creation_act' => 'ato de criação', 'authorization_act' => 'autorização do curso', 'legal_basis' => 'fundamento legal',
            'registry_resolution' => 'norma do registro', 'front_address' => 'endereço da frente', 'back_address' => 'endereço do verso',
            'state_heading' => 'estado do cabeçalho', 'education_system' => 'sistema de ensino'];

        $missing = collect($labels)->filter(fn ($label, $field) => blank($this->{$field}))->values()->all();
        if (! $this->modules || collect($this->modules)->contains(fn ($m) => empty($m['components']))) {
            $missing[] = 'módulos e componentes do curso';
        }
        $ids = $this->componentDefinitions()->pluck('catalog_component_id');
        $validIds = HistoryCurriculumComponent::whereIn('id', $ids->filter())->where('knowledge_area_id', $this->knowledge_area_id)
            ->where('education_stage', 'tecnico')->where('formation', 'Formação Técnica Profissional')->pluck('id');
        if (! $this->knowledge_area_id || $ids->contains(fn ($id) => ! $id || ! $validIds->contains($id))) {
            $missing[] = 'vínculos com o cadastro curricular da área do curso';
        }

        return $missing;
    }
}
