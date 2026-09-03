<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonContact extends Model
{
    use Auditable, HasTitleCaseAttributes;

    public const TYPE_MOTHER = 'mae';
    public const TYPE_FATHER = 'pai';
    public const TYPE_LEGAL_GUARDIAN = 'responsavel_legal';
    public const TYPE_GRANDMOTHER = 'avo';
    public const TYPE_GRANDFATHER = 'avo_masculino';
    public const TYPE_SIBLING = 'irmao';
    public const TYPE_OTHER = 'outro';

    public const TYPE_LABELS = [
        self::TYPE_MOTHER => 'Mãe',
        self::TYPE_FATHER => 'Pai',
        self::TYPE_LEGAL_GUARDIAN => 'Responsável legal',
        self::TYPE_GRANDMOTHER => 'Avó',
        self::TYPE_GRANDFATHER => 'Avô',
        self::TYPE_SIBLING => 'Irmã(o)',
        self::TYPE_OTHER => 'Outro vínculo',
    ];

    protected $fillable = [
        'person_id',
        'name',
        'relationship_type',
        'cpf',
        'nis',
        'phone',
        'secondary_phone',
        'email',
        'legal_guardian',
        'emergency_contact',
        'notes',
        'legacy_source',
        'legacy_metadata',
    ];

    protected function casts(): array
    {
        return [
            'legal_guardian' => 'boolean',
            'emergency_contact' => 'boolean',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function label(): string
    {
        $label = __('roles.contacts.'.$this->relationship_type);

        return $label === 'roles.contacts.'.$this->relationship_type
            ? (self::TYPE_LABELS[$this->relationship_type] ?? $this->relationship_type)
            : $label;
    }
}
