<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperationFailure extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public const CATEGORIES = ['validation' => 'Dados ou regras pendentes', 'authorization' => 'Permissão negada', 'http' => 'Falha na solicitação', 'exception' => 'Erro do sistema', 'browser' => 'Mensagem na tela', 'network' => 'Falha de comunicação'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'context' => 'array', 'actor_user_id' => 'integer', 'school_id' => 'integer', 'status_code' => 'integer'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
