<?php

namespace App\Support\Reports;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\AuditLogPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportDefinition
{
    public const TYPES = ['schools', 'people', 'roles', 'audit-logs'];

    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly array $headings,
        public readonly Collection $rows,
        public readonly array $filters = [],
        public readonly ?string $search = null,
    ) {}

    /**
     * @param array<string, mixed> $filters
     */
    public static function make(string $type, User $user, array $filters = [], ?string $search = null): self
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return match ($type) {
            'schools' => self::schools($user, $filters, $search),
            'people' => self::people($user, $filters, $search),
            'roles' => self::roles($user, $filters, $search),
            'audit-logs' => self::auditLogs($user, $filters, $search),
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function schools(User $user, array $filters, ?string $search): self
    {
        $rows = School::query()
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $user->manageableSchoolIds()))
            ->when(($filters['situacao'] ?? '') !== '', fn (Builder $query) => $query->where('active', ($filters['situacao'] ?? '') === '1'))
            ->when(filled($filters['uf'] ?? null), fn (Builder $query) => $query->where('state', $filters['uf']))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('inep', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                $school->name,
                $school->city,
                $school->state,
                $school->inep,
                $school->active ? 'Ativa' : 'Inativa',
            ]);

        return new self('schools', 'Relatório de Escolas', ['Nome', 'Cidade', 'UF', 'INEP', 'Situação'], $rows, $filters, $search);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function people(User $user, array $filters, ?string $search): self
    {
        $rows = Person::query()
            ->with('schoolRoles.school')
            ->when(! $user->isAdministrator(), function (Builder $query) use ($user): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $user->manageableSchoolIds()));
            })
            ->when(($filters['situacao'] ?? '') !== '', fn (Builder $query) => $query->where('active', ($filters['situacao'] ?? '') === '1'))
            ->when(filled($filters['papel'] ?? null) || filled($filters['escola'] ?? null), function (Builder $query) use ($filters): void {
                $query->whereHasActiveSchoolRole($filters['papel'] ?? null, $filters['escola'] ?? null);
            })
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('institutional_email', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->get()
            ->map(fn (Person $person): array => [
                $person->full_name,
                $person->institutional_email,
                $person->cpf,
                $person->phone,
                $person->active ? 'Ativa' : 'Inativa',
            ]);

        return new self('people', 'Relatório de Pessoas', ['Nome', 'E-mail institucional', 'CPF', 'Telefone', 'Situação'], $rows, $filters, $search);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function roles(User $user, array $filters, ?string $search): self
    {
        $rows = PersonSchoolRole::query()
            ->with(['person', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()))
            ->when(filled($filters['papel'] ?? null), fn (Builder $query) => $query->where('role', $filters['papel']))
            ->when(($filters['escola'] ?? '') !== '', function (Builder $query) use ($filters): void {
                ($filters['escola'] ?? '') === 'global'
                    ? $query->whereNull('school_id')
                    : $query->where('school_id', (int) $filters['escola']);
            })
            ->when(($filters['situacao'] ?? '') !== '', fn (Builder $query) => ($filters['situacao'] ?? '') === '1'
                ? self::activeRoleForDate($query)
                : self::inactiveRoleForDate($query))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('role', 'like', "%{$search}%")
                        ->orWhereHas('person', fn (Builder $person) => $person->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('school', fn (Builder $school) => $school->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('role')
            ->get()
            ->map(fn (PersonSchoolRole $role): array => [
                $role->person?->full_name,
                $role->label(),
                $role->school?->name ?? 'Global',
                $role->started_at?->format('d/m/Y'),
                $role->ended_at?->format('d/m/Y') ?? 'Indeterminado',
                $role->isActiveForDate() ? 'Ativo' : 'Inativo',
            ]);

        return new self('roles', 'Relatório de Vínculos', ['Pessoa', 'Papel', 'Escola', 'Início', 'Fim', 'Situação'], $rows, $filters, $search);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function auditLogs(User $user, array $filters, ?string $search): self
    {
        $timezone = $user->auditTimezone();
        $rows = AuditLog::query()
            ->with(['actorUser', 'actorPerson', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()))
            ->when(filled($filters['acao'] ?? null), fn (Builder $query) => $query->where('action', $filters['acao']))
            ->when(($filters['escola'] ?? '') !== '', function (Builder $query) use ($filters): void {
                ($filters['escola'] ?? '') === 'global'
                    ? $query->whereNull('school_id')
                    : $query->where('school_id', (int) $filters['escola']);
            })
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhere('auditable_id', 'like', "%{$search}%")
                        ->orWhereHas('actorPerson', fn (Builder $person) => $person->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('actorUser', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('school', fn (Builder $school) => $school->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (AuditLog $auditLog): array => [
                $auditLog->created_at?->timezone($timezone)->format('d/m/Y H:i:s'),
                $auditLog->actorPerson?->full_name ?? $auditLog->actorUser?->name ?? 'Sistema',
                AuditLogPresenter::actionLabel($auditLog->action),
                AuditLogPresenter::recordLabel($auditLog),
                $auditLog->school?->name ?? '-',
            ]);

        return new self('audit-logs', 'Relatório de Auditoria', ['Quando', 'Quem', 'Ação', 'Registro', 'Escola'], $rows, $filters, $search);
    }

    private static function activeRoleForDate(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }

    private static function inactiveRoleForDate(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('active', false)
                ->orWhereDate('started_at', '>', now()->toDateString())
                ->orWhereDate('ended_at', '<', now()->toDateString());
        });
    }
}
