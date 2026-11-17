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

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'active' => 'boolean', 'authorization_starts_at' => 'date', 'authorization_ends_at' => 'date'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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

        return collect($labels)->filter(fn ($label, $field) => blank($this->{$field}))->values()->all();
    }
}
