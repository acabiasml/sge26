<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasTitleCaseAttributes, Notifiable;

    public const DEFAULT_AUDIT_TIMEZONE = 'America/Sao_Paulo';

    public const AUDIT_TIMEZONES = [
        'America/Sao_Paulo' => 'Brasília',
        'America/Manaus' => 'Manaus',
        'America/Rio_Branco' => 'Rio Branco',
        'America/Noronha' => 'Fernando de Noronha',
        'UTC' => 'UTC',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'name',
        'email',
        'audit_timezone',
        'locale',
        'google_id',
        'avatar',
        'email_verified_at',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isAdministrator(): bool
    {
        return $this->hasActiveRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
    }

    public function isManager(): bool
    {
        return $this->hasActiveRole(PersonSchoolRole::ROLE_MANAGER);
    }

    public function hasActiveRole(string $role, ?int $schoolId = null): bool
    {
        if (! $this->person) {
            return false;
        }

        return $this->person->schoolRoles()
            ->where('role', $role)
            ->where('active', true)
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->where(function ($query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            })
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function manageableSchoolIds(): array
    {
        if (! $this->person) {
            return [];
        }

        return $this->person->schoolRoles()
            ->where('role', PersonSchoolRole::ROLE_MANAGER)
            ->where('active', true)
            ->whereNotNull('school_id')
            ->where(function ($query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            })
            ->pluck('school_id')
            ->map(fn ($schoolId): int => (int) $schoolId)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function visibleSchoolIds(): array
    {
        if (! $this->person) {
            return [];
        }

        return $this->person->schoolRoles()
            ->where('active', true)
            ->whereNotNull('school_id')
            ->where(function ($query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            })
            ->pluck('school_id')
            ->map(fn ($schoolId): int => (int) $schoolId)
            ->unique()
            ->values()
            ->all();
    }

    public function canManageSchools(): bool
    {
        return $this->isAdministrator();
    }

    public function canManageSchool(?int $schoolId): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        if ($schoolId === null) {
            return false;
        }

        return $this->hasActiveRole(PersonSchoolRole::ROLE_MANAGER, $schoolId);
    }

    public function canManagePeople(?int $schoolId = null): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        if ($schoolId === null) {
            return $this->isManager();
        }

        return $this->canManageSchool($schoolId);
    }

    public function canAssignRoles(?int $schoolId = null): bool
    {
        return $this->canManagePeople($schoolId);
    }

    public function hasTeachingDiaries(): bool
    {
        if (! $this->person_id) {
            return false;
        }

        return SchoolClassComponent::query()
            ->where(function ($query): void {
                $query->where('teacher_person_id', $this->person_id)
                    ->orWhereHas('substitutions', function ($substitutions): void {
                        $substitutions
                            ->where('substitute_teacher_person_id', $this->person_id)
                            ->whereDate('starts_at', '<=', now()->toDateString())
                            ->where(function ($period): void {
                                $period->whereNull('ends_at')
                                    ->orWhereDate('ends_at', '>=', now()->toDateString());
                            });
                    });
            })
            ->where('active', true)
            ->whereHas('schoolClass', fn ($query) => $query->where('active', true))
            ->whereHas('component.course.academicYear', fn ($query) => $query->whereNotNull('approved_at')->where('active', true))
            ->exists();
    }

    public function hasStudentMap(): bool
    {
        if (! $this->person_id) {
            return false;
        }

        return $this->hasActiveRole(PersonSchoolRole::ROLE_STUDENT)
            || $this->person?->studentEnrollments()->exists() === true;
    }

    public function auditTimezone(): string
    {
        return array_key_exists($this->audit_timezone ?? '', self::AUDIT_TIMEZONES)
            ? $this->audit_timezone
            : self::DEFAULT_AUDIT_TIMEZONE;
    }

    public function activeRoleLabel(): string
    {
        $role = $this->person?->loadMissing('schoolRoles.school')->primaryActiveRole();

        if (! $role) {
            return 'Sem vínculo ativo';
        }

        $period = ($role->started_at?->format('d/m/Y') ?? 'sem início')
            .' até '
            .($role->ended_at?->format('d/m/Y') ?? 'indeterminado');

        return $role->label()
            .($role->school ? ' / '.$role->school->name : ' / Global')
            .' ('.$period.')';
    }
}
