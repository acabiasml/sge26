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

    public const INTERFACE_THEMES = [
        'beaba' => 'theme.beathema',
        'govbr' => 'theme.govbr',
        'aurora-light' => 'theme.aurora_light',
        'instagram-light' => 'theme.instagram_light',
        'tiktok-light' => 'theme.tiktok_light',
        'gmail-light' => 'theme.gmail_light',
        'outlook-light' => 'theme.outlook_light',
        'windows-light' => 'theme.windows',
        'ubuntu-light' => 'theme.ubuntu',
    ];

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
        'theme',
        'preferred_role_view',
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
        if ($this->currentView() !== $role) {
            return false;
        }

        return $schoolId === null || $this->hasActualActiveRole($role, $schoolId);
    }

    /** @return list<string> */
    public function availableViews(): array
    {
        if (! $this->person) {
            return [];
        }

        return $this->person->schoolRoles()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            })
            ->pluck('role')
            ->unique()
            ->filter(fn (string $role): bool => isset(PersonSchoolRole::ROLE_PRIORITY[$role]))
            ->sortByDesc(fn (string $role): int => PersonSchoolRole::ROLE_PRIORITY[$role])
            ->values()
            ->all();
    }

    public function currentView(): ?string
    {
        $views = $this->availableViews();

        return in_array($this->preferred_role_view, $views, true)
            ? $this->preferred_role_view
            : ($views[0] ?? null);
    }

    private function hasActualActiveRole(string $role, int $schoolId): bool
    {
        return $this->person->schoolRoles()
            ->where('role', $role)
            ->where('school_id', $schoolId)
            ->where('active', true)
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
        if (! $this->person || $this->currentView() !== PersonSchoolRole::ROLE_MANAGER) {
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

        $view = $this->currentView();

        return $this->person->schoolRoles()
            ->where('active', true)
            ->whereNotNull('school_id')
            ->when($view !== null, fn ($query) => $query->where('role', $view))
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
        $view = $this->currentView();
        if ($view !== null && $view !== PersonSchoolRole::ROLE_TEACHER) {
            return false;
        }

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
        $view = $this->currentView();
        if ($view !== null && $view !== PersonSchoolRole::ROLE_STUDENT) {
            return false;
        }

        if (! $this->person_id) {
            return false;
        }

        return $this->hasActiveRole(PersonSchoolRole::ROLE_STUDENT)
            || $this->person?->studentEnrollments()->exists() === true;
    }

    public function interfaceTheme(): string
    {
        $theme = (string) $this->theme;
        if (str_ends_with($theme, '-dark')) {
            $theme = substr($theme, 0, -5).'-light';
        }

        return array_key_exists($theme, self::INTERFACE_THEMES) ? $theme : 'beaba';
    }

    public function auditTimezone(): string
    {
        return array_key_exists($this->audit_timezone ?? '', self::AUDIT_TIMEZONES)
            ? $this->audit_timezone
            : self::DEFAULT_AUDIT_TIMEZONE;
    }

    public function activeRoleLabel(): string
    {
        $view = $this->currentView();
        $role = $this->person?->loadMissing('schoolRoles.school')->schoolRoles
            ->first(fn (PersonSchoolRole $role): bool => $role->role === $view && $role->isActiveForDate());

        if (! $role) {
            return __('roles.no_active_role');
        }

        return $role->label()
            .($role->school ? ' / '.$role->school->name : ' / '.__('roles.global'))
            .($role->started_at ? ' ('.$role->started_at->format('d/m/Y').')' : '');
    }
}
