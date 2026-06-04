<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'legacy_id',
        'name',
        'legal_name',
        'cnpj',
        'inep',
        'phone',
        'email',
        'address',
        'district',
        'number',
        'city',
        'state',
        'postal_code',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }
}
