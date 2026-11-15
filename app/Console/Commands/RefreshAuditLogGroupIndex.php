<?php

namespace App\Console\Commands;

use App\Models\PersonSchoolRole;
use App\Models\User;
use App\Support\AuditLogGroupIndex;
use App\Support\AuditLogGroups;
use Illuminate\Console\Command;

class RefreshAuditLogGroupIndex extends Command
{
    protected $signature = 'audit:refresh-index {--rebuild : Recalcular os índices auxiliares a partir dos registros originais}
        {--benchmark : Medir a consulta da primeira página sem exibir dados pessoais}';

    protected $description = 'Prepara os agrupamentos da auditoria por escopo sem alterar os registros originais';

    public function handle(AuditLogGroupIndex $index): int
    {
        if (! $index->available()) {
            $this->error('A migration do índice da auditoria ainda não foi executada.');

            return self::FAILURE;
        }
        if ($this->option('rebuild')) {
            $index->invalidate();
        }

        $scopes = [];
        $started = hrtime(true);
        User::query()->whereHas('person.schoolRoles', fn ($query) => $query->whereIn('role', [
            PersonSchoolRole::ROLE_ADMINISTRATOR, PersonSchoolRole::ROLE_MANAGER,
        ]))->each(function (User $user) use ($index, &$scopes): void {
            foreach (array_intersect($user->availableViews(), [PersonSchoolRole::ROLE_ADMINISTRATOR, PersonSchoolRole::ROLE_MANAGER]) as $view) {
                $user->preferred_role_view = $view;
                $scope = $index->scopeKey($user);
                if (! isset($scopes[$scope])) {
                    $index->ensure($user);
                    $scopes[$scope] = true;
                    if ($this->option('benchmark')) {
                        $start = hrtime(true);
                        $page = AuditLogGroups::query($user)->select('audit_logs.id')
                            ->orderByDesc('audit_logs.created_at')->paginate(10);
                        $this->line(sprintf('Escopo %d: %d grupos; primeira página em %.1f ms.', count($scopes), $page->total(), (hrtime(true) - $start) / 1e6));
                    }
                }
            }
        });
        $this->info(sprintf('Índices preparados: %d escopos em %.0f ms.', count($scopes), (hrtime(true) - $started) / 1e6));

        return self::SUCCESS;
    }
}
