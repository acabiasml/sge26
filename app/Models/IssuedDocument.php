<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'uuid',
        'verification_code',
        'type',
        'person_id',
        'school_id',
        'issued_by_user_id',
        'payload',
        'file_path',
        'issued_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
