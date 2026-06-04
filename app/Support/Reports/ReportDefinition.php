<?php

namespace App\Support\Reports;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
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
    ) {}

    public static function make(string $type, User $user): self
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return match ($type) {
            'schools' => self::schools($user),
            'people' => self::people($user),
            'roles' => self::roles($user),
            'audit-logs' => self::auditLogs($user),
        };
    }

    private static function schools(User $user): self
    {
        $rows = School::query()
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $user->manageableSchoolIds()))
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                $school->name,
                $school->city,
                $school->state,
                $school->inep,
                $school->active ? 'Ativa' : 'Inativa',
            ]);

        return new self('schools', 'Relatório de Escolas', ['Nome', 'Cidade', 'UF', 'INEP', 'Situação'], $rows);
    }

    private static function people(User $user): self
    {
        $rows = Person::query()
            ->with('schoolRoles.school')
            ->when(! $user->isAdministrator(), function (Builder $query) use ($user): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $user->manageableSchoolIds()));
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

        return new self('people', 'Relatório de Pessoas', ['Nome', 'E-mail institucional', 'CPF', 'Telefone', 'Situação'], $rows);
    }

    private static function roles(User $user): self
    {
        $rows = PersonSchoolRole::query()
            ->with(['person', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()))
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

        return new self('roles', 'Relatório de Vínculos', ['Pessoa', 'Papel', 'Escola', 'Início', 'Fim', 'Situação'], $rows);
    }

    private static function auditLogs(User $user): self
    {
        $timezone = $user->auditTimezone();
        $rows = AuditLog::query()
            ->with(['actorUser', 'actorPerson', 'school'])
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()))
            ->latest('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (AuditLog $auditLog): array => [
                $auditLog->created_at?->timezone($timezone)->format('d/m/Y H:i:s'),
                $auditLog->actorPerson?->full_name ?? $auditLog->actorUser?->name ?? 'Sistema',
                $auditLog->action,
                class_basename($auditLog->auditable_type).' #'.$auditLog->auditable_id,
                $auditLog->school?->name ?? '-',
            ]);

        return new self('audit-logs', 'Relatório de Auditoria', ['Quando', 'Quem', 'Ação', 'Registro', 'Escola'], $rows);
    }
}
