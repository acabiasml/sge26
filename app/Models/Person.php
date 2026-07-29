<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
        'birth_city',
        'birth_state',
        'nationality',
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
            'birth_city',
            'nationality',
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
            && filled($this->birth_city)
            && filled($this->birth_state)
            && filled($this->nationality)
            && filled($this->mother_name)
            && filled($this->address)
            && filled($this->city)
            && filled($this->state)
            && filled($this->postal_code)
            && filled($this->profile_completed_at);
    }

    public function hasRequiredIdentityForOfficialUse(): bool
    {
        return filled($this->cpf);
    }

    public function hasRequiredIdentityForSchoolDocuments(): bool
    {
        return collect([
            $this->full_name,
            $this->cpf,
            $this->birth_date,
            $this->birth_city,
            $this->birth_state,
            $this->nationality,
            $this->mother_name,
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
        ])->every(fn ($value): bool => filled($value));
    }

    /**
     * @return list<string>
     */
    public function missingSchoolDocumentFields(): array
    {
        $fields = [
            'full_name' => 'nome completo',
            'cpf' => 'CPF',
            'birth_date' => 'data de nascimento',
            'mother_name' => 'nome da mãe',
        ];

        return collect($fields)
            ->filter(fn (string $label, string $field): bool => blank($this->{$field}))
            ->values()
            ->all();
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

    public function academicHistories(): HasMany
    {
        return $this->hasMany(StudentAcademicHistory::class);
    }

    public function issuedDocuments(): HasMany
    {
        return $this->hasMany(IssuedDocument::class);
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

    public function hasActiveRoleForDate(mixed $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        if ($this->relationLoaded('schoolRoles')) {
            return $this->schoolRoles->contains(fn (PersonSchoolRole $role): bool => $role->isActiveForDate($date));
        }

        return $this->schoolRoles()
            ->where('active', true)
            ->where(function (Builder $roles) use ($date): void {
                $roles->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $date);
            })
            ->where(function (Builder $roles) use ($date): void {
                $roles->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $date);
            })
            ->exists();
    }

    public function syncActiveFromRoles(): bool
    {
        $active = $this->hasActiveRoleForDate();

        if ((bool) $this->active === $active) {
            return true;
        }

        return $this->forceFill(['active' => $active])->save();
    }

    public function scopeWhereHasActiveSchoolRole(Builder $query, ?string $role = null, mixed $schoolId = null): Builder
    {
        return $query->whereHasSchoolRole($role, $schoolId, 'ativos');
    }

    public function scopeWhereActiveByRoles(Builder $query): Builder
    {
        return $query->whereHas('schoolRoles', fn (Builder $roles) => self::activeSchoolRoleForDate($roles));
    }

    public function scopeWhereInactiveByRoles(Builder $query): Builder
    {
        return $query->whereDoesntHave('schoolRoles', fn (Builder $roles) => self::activeSchoolRoleForDate($roles));
    }

    public function scopeWhereHasSchoolRole(Builder $query, ?string $role = null, mixed $schoolId = null, ?string $status = null): Builder
    {
        if ($status === 'sem') {
            if (filled($role) || filled($schoolId)) {
                return $query->whereRaw('0 = 1');
            }

            return $query->whereDoesntHave('schoolRoles');
        }

        return $query->whereHas('schoolRoles', function (Builder $roles) use ($role, $schoolId, $status): void {
            $roles
                ->when(filled($role), fn (Builder $roles) => $roles->where('role', $role))
                ->when(filled($schoolId), fn (Builder $roles) => $roles->where('school_id', (int) $schoolId))
                ->when($status === 'ativos', fn (Builder $roles) => self::activeSchoolRoleForDate($roles))
                ->when($status === 'inativos', fn (Builder $roles) => self::inactiveSchoolRoleForDate($roles));
        });
    }

    private static function activeSchoolRoleForDate(Builder $roles): Builder
    {
        return $roles
            ->where('active', true)
            ->where(function (Builder $roles): void {
                $roles->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $roles): void {
                $roles->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }

    private static function inactiveSchoolRoleForDate(Builder $roles): Builder
    {
        return $roles->where(function (Builder $roles): void {
            $roles->where('active', false)
                ->orWhereDate('started_at', '>', now()->toDateString())
                ->orWhereDate('ended_at', '<', now()->toDateString());
        });
    }
}
