<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRelationship extends Model
{
    use Auditable;

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
        'related_person_id',
        'relationship_type',
        'legal_guardian',
        'emergency_contact',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'legal_guardian' => 'boolean',
            'emergency_contact' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->relationship_type] ?? $this->relationship_type;
    }
}
