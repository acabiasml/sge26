<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait ImmutableRegistryRecord
{
    protected static function bootImmutableRegistryRecord(): void
    {
        $reject = fn () => throw ValidationException::withMessages(['registry' => __('O acervo é permanente. Registre uma averbação ou um cancelamento, sem editar ou excluir o original.')]);
        static::updating($reject);
        static::deleting($reject);
    }
}
