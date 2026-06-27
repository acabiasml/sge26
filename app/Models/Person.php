<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'mother_name',
        'father_name',
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
            'mother_name',
            'father_name',
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
            && filled($this->mother_name)
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

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
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

    public function scopeWhereHasActiveSchoolRole(Builder $query, ?string $role = null, mixed $schoolId = null): Builder
    {
        return $query->whereHas('schoolRoles', function (Builder $roles) use ($role, $schoolId): void {
            $roles
                ->when(filled($role), fn (Builder $roles) => $roles->where('role', $role))
                ->when(filled($schoolId), fn (Builder $roles) => $roles->where('school_id', (int) $schoolId))
                ->where('active', true)
                ->where(function (Builder $roles): void {
                    $roles->whereNull('started_at')
                        ->orWhereDate('started_at', '<=', now()->toDateString());
                })
                ->where(function (Builder $roles): void {
                    $roles->whereNull('ended_at')
                        ->orWhereDate('ended_at', '>=', now()->toDateString());
                });
        });
    }
}
