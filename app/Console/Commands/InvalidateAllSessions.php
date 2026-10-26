<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvalidateAllSessions extends Command
{
    protected $signature = 'sessions:invalidate-all {--force : Confirma a desconexão de todos os usuários}';

    protected $description = 'Invalida todas as sessões e os tokens antigos de lembrar do usuário.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Use --force para confirmar a desconexão de todos os usuários.');

            return self::FAILURE;
        }

        if (config('session.driver') !== 'database') {
            $this->error('Esta operação exige o armazenamento de sessões no banco de dados.');

            return self::FAILURE;
        }

        $connection = DB::connection(config('session.connection'));
        $table = config('session.table', 'sessions');
        $activeUsers = $connection->table($table)->whereNotNull('user_id')->count();
        $removedSessions = $connection->table($table)->delete();
        $revokedTokens = DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);

        $this->info("Sessões removidas: {$removedSessions} ({$activeUsers} autenticadas). Tokens antigos invalidados: {$revokedTokens}.");

        return self::SUCCESS;
    }
}
