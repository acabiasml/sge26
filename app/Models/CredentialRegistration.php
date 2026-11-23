<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRegistryRecord;
use Illuminate\Database\Eloquent\Model;

class CredentialRegistration extends Model
{
    use ImmutableRegistryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['credential_book_id' => 'integer', 'issued_document_id' => 'integer', 'number' => 'integer', 'folio' => 'integer', 'registered_by_user_id' => 'integer', 'snapshot' => 'array', 'registered_on' => 'date', 'created_at' => 'datetime'];
    }

    public function book()
    {
        return $this->belongsTo(CredentialBook::class, 'credential_book_id');
    }

    public function document()
    {
        return $this->belongsTo(IssuedDocument::class, 'issued_document_id');
    }

    public function events()
    {
        return $this->hasMany(CredentialRegistryEvent::class);
    }

    public function files()
    {
        return $this->hasMany(CredentialRegistryFile::class);
    }

    public function registrar()
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
