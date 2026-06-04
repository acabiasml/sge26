<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created');
        });

        static::updated(function (Model $model): void {
            $model->writeAuditLog('updated');
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted');
        });
    }

    protected function writeAuditLog(string $action): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $user = $this->auditActorUser();
        $role = $this->auditActorRole($user);
        $changes = $this->auditableChanges($action);

        AuditLog::query()->create([
            'actor_user_id' => $user?->id,
            'actor_person_id' => $user?->person_id,
            'school_id' => $this->auditSchoolId() ?? $role?->school_id,
            'actor_role' => $role?->role,
            'actor_position' => $role?->position,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'old_values' => $changes['old'],
            'new_values' => $changes['new'],
            'metadata' => null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    protected function auditActorUser(): ?User
    {
        $requestUser = app()->bound('request') ? request()->user() : null;

        if ($requestUser instanceof User) {
            return $requestUser;
        }

        $authUser = Auth::guard('web')->user() ?? Auth::user();

        return $authUser instanceof User ? $authUser : null;
    }

    protected function auditActorRole(?User $user): ?PersonSchoolRole
    {
        if (! $user?->person_id) {
            return null;
        }

        $schoolId = $this->auditSchoolId();

        return $user->person?->schoolRoles()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            })
            ->when(
                $schoolId !== null,
                fn ($query) => $query->orderByRaw('case when school_id = ? then 0 else 1 end', [$schoolId])
            )
            ->orderByRaw(
                'case role when ? then 50 when ? then 40 when ? then 30 when ? then 20 when ? then 10 else 0 end desc',
                [
                    PersonSchoolRole::ROLE_ADMINISTRATOR,
                    PersonSchoolRole::ROLE_MANAGER,
                    PersonSchoolRole::ROLE_TEACHER,
                    PersonSchoolRole::ROLE_EMPLOYEE,
                    PersonSchoolRole::ROLE_STUDENT,
                ]
            )
            ->first();
    }

    /**
     * @return array{old: array<string, mixed>|null, new: array<string, mixed>|null}
     */
    protected function auditableChanges(string $action): array
    {
        if ($action === 'created') {
            return [
                'old' => null,
                'new' => $this->auditAttributes($this->getAttributes()),
            ];
        }

        if ($action === 'deleted') {
            return [
                'old' => $this->auditAttributes($this->getOriginal()),
                'new' => null,
            ];
        }

        $old = [];
        $new = [];

        foreach (array_keys($this->getChanges()) as $attribute) {
            if ($this->shouldHideFromAudit($attribute)) {
                continue;
            }

            $old[$attribute] = $this->getOriginal($attribute);
            $new[$attribute] = $this->getAttribute($attribute);
        }

        return [
            'old' => $old ?: null,
            'new' => $new ?: null,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function auditAttributes(array $attributes): array
    {
        return collect($attributes)
            ->reject(fn (mixed $value, string $key): bool => $this->shouldHideFromAudit($key))
            ->all();
    }

    protected function shouldHideFromAudit(string $attribute): bool
    {
        return in_array($attribute, [
            'password',
            'remember_token',
        ], true);
    }

    protected function auditSchoolId(): ?int
    {
        return array_key_exists('school_id', $this->getAttributes())
            ? $this->getAttribute('school_id')
            : null;
    }
}
