<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

        $driver = config('session.driver');

        if (! in_array($driver, ['database', 'file'], true)) {
            $this->error("O armazenamento de sessões '{$driver}' não é suportado por esta operação.");

            return self::FAILURE;
        }

        if ($driver === 'database') {
            $connection = DB::connection(config('session.connection'));
            $table = config('session.table', 'sessions');
            $removedSessions = $connection->table($table)->delete();
        } else {
            $path = config('session.files');

            if (! File::isDirectory($path)) {
                $this->error('O diretório de sessões configurado não existe.');

                return self::FAILURE;
            }

            $removedSessions = 0;

            foreach (File::files($path) as $file) {
                if (! preg_match('/^[a-zA-Z0-9]{40}$/', $file->getFilename())) {
                    continue;
                }

                if (! File::delete($file->getPathname())) {
                    $this->error('Não foi possível apagar todos os arquivos de sessão.');

                    return self::FAILURE;
                }

                $removedSessions++;
            }
        }

        $revokedTokens = DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);

        $this->info("Sessões removidas: {$removedSessions}. Tokens antigos invalidados: {$revokedTokens}.");

        return self::SUCCESS;
    }
}
