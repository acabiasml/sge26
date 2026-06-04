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
        'founded_at',
        'phone',
        'email',
        'website',
        'letterhead_text',
        'logo_path',
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
            'founded_at' => 'date',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
