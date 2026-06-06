<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'legacy_id',
        'legacy_source',
        'legacy_code',
        'student_inep',
        'legacy_metadata',
        'full_name',
        'social_name',
        'cpf',
        'birth_date',
        'institutional_email',
        'personal_email',
        'phone',
        'address',
        'number',
        'district',
        'city',
        'state',
        'postal_code',
        'address_complement',
        'active',
        'profile_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
            'profile_completed_at' => 'datetime',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'full_name',
            'social_name',
            'address',
            'district',
            'city',
            'address_complement',
        ];
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->cpf)
            && filled($this->birth_date)
            && filled($this->phone)
            && filled($this->profile_completed_at);
    }

    public function hasRequiredIdentityForOfficialUse(): bool
    {
        return filled($this->cpf)
            && filled($this->institutional_email);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function schoolRoles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(PersonRelationship::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PersonContact::class);
    }

    public function inverseRelationships(): HasMany
    {
        return $this->hasMany(PersonRelationship::class, 'related_person_id');
    }

    public function primaryActiveRole(): ?PersonSchoolRole
    {
        return $this->schoolRoles
            ->filter(fn (PersonSchoolRole $role): bool => $role->isActiveForDate())
            ->sortByDesc(fn (PersonSchoolRole $role): int => PersonSchoolRole::ROLE_PRIORITY[$role->role] ?? 0)
            ->first();
    }
}
