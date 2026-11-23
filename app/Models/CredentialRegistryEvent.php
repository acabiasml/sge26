<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRegistryRecord;
use Illuminate\Database\Eloquent\Model;

class CredentialRegistryEvent extends Model
{
    use ImmutableRegistryRecord;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['credential_book_id' => 'integer', 'credential_registration_id' => 'integer', 'actor_user_id' => 'integer', 'data' => 'array', 'occurred_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
