<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'legacy_id',
        'full_name',
        'social_name',
        'cpf',
        'birth_date',
        'institutional_email',
        'personal_email',
        'phone',
        'active',
        'profile_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->cpf)
            && filled($this->birth_date)
            && filled($this->phone)
            && filled($this->profile_completed_at);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function schoolRoles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }

    public function primaryActiveRole(): ?PersonSchoolRole
    {
        return $this->schoolRoles
            ->filter(fn (PersonSchoolRole $role): bool => $role->isActiveForDate())
            ->sortByDesc(fn (PersonSchoolRole $role): int => PersonSchoolRole::ROLE_PRIORITY[$role->role] ?? 0)
            ->first();
    }
}
