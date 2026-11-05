<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UserLastAccess
{
    public static function available(): bool
    {
        // O código pode chegar ao servidor antes da migration durante o deploy.
        return once(static fn (): bool => Schema::hasColumn('users', 'last_access_at'));
    }

    public function record(User $user): void
    {
        if (! self::available()) {
            return;
        }

        $at = now();
        $changed = DB::table('users')->where('id', $user->id)
            ->where(fn (Builder $query) => $query->whereNull('last_access_at')->orWhere('last_access_at', '<', $at))
            ->update(['last_access_at' => $at]);

        if ($changed) {
            // Acesso não é alteração cadastral nem deve gerar auditorias de edição.
            $user->setAttribute('last_access_at', $at)->syncOriginalAttribute('last_access_at');
        }
    }
}
