<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ImmutableRegistryRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureVersion extends Model
{
    use Auditable, ImmutableRegistryRecord;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['complete' => 'boolean'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(IssuedDocument::class, 'issued_document_id');
    }

    protected function auditSchoolId(): ?int
    {
        return $this->document?->school_id;
    }
}
