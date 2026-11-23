<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRegistryRecord;
use Illuminate\Database\Eloquent\Model;

class CredentialRegistryFile extends Model
{
    use ImmutableRegistryRecord;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['credential_book_id' => 'integer', 'credential_registration_id' => 'integer', 'uploaded_by_user_id' => 'integer', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function book()
    {
        return $this->belongsTo(CredentialBook::class, 'credential_book_id');
    }

    public function registration()
    {
        return $this->belongsTo(CredentialRegistration::class, 'credential_registration_id');
    }
}
