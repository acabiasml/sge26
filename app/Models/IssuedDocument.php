<?php

namespace App\Models;

use App\Http\Middleware\ArchivePdfForSignature;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class IssuedDocument extends Model
{
    use Auditable;

    public const CREDENTIAL_TYPES = ['free-course-certificate', 'technical-intermediate-certificate', 'technical-course-diploma', 'technical-course-diploma-preview', 'high-school-completion-certificate'];

    protected static function booted(): void
    {
        static::created(function (self $document): void {
            $attributes = request()->attributes;
            $key = ArchivePdfForSignature::CAPTURE;
            if ($attributes->has($key)) {
                $attributes->set($key, [...$attributes->get($key), $document->id]);
            }
        });
        static::updating(function (self $document): void {
            if (($document->registration()->exists() || $document->signatureVersions()->exists()) && array_diff(array_keys($document->getDirty()), ['revoked_at', 'updated_at'])) {
                throw ValidationException::withMessages(['registry' => 'O documento registrado é imutável. Emita outra versão e preserve o original.']);
            }
        });
        static::deleting(function (self $document): void {
            if ($document->registration()->exists()) {
                throw ValidationException::withMessages(['registry' => 'O documento integra um livro permanente. Use o cancelamento no Livro Digital.']);
            }
            if ($document->signatureVersions()->exists()) {
                throw ValidationException::withMessages(['document' => 'Existe uma versão assinada arquivada para este documento.']);
            }
            if (in_array($document->type, self::CREDENTIAL_TYPES, true)) {
                abort_unless(auth()->user()?->isAdministrator(), 403);
            }
        });
    }

    protected $fillable = [
        'batch_uuid',
        'file_sha256',
        'registration_key',
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

    public function signatureVersions(): HasMany
    {
        return $this->hasMany(DocumentSignatureVersion::class);
    }

    public function registration(): HasOne
    {
        return $this->hasOne(CredentialRegistration::class);
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
