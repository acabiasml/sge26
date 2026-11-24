<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CredentialBook extends Model
{
    use Auditable;

    public const KINDS = [
        'certificate' => 'Certificados de cursos',
        'diploma' => 'Diplomas técnicos',
        'high-school' => 'Conclusão do Ensino Médio',
        'history' => 'Históricos escolares',
        'final-results' => 'Atas de resultados finais',
        'transfer' => 'Transferências expedidas e recebidas',
        'special-process' => 'Processos especiais',
        'delivery' => 'Entrega de certificados e diplomas',
        'visit' => 'Termos de visita',
        'administrative' => 'Reuniões administrativas',
        'pedagogical' => 'Reuniões pedagógicas',
        'disposal' => 'Eliminação de documentos',
        'inventory' => 'Livro tombo',
        'correspondence' => 'Protocolo de remessa e recebimento',
    ];

    protected $guarded = ['id'];

    public function isPhysical(): bool
    {
        return $this->medium === 'physical';
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'reference_year' => 'integer', 'created_by_user_id' => 'integer', 'opened_on' => 'date', 'closed_at' => 'datetime', 'first_number' => 'integer', 'next_number' => 'integer', 'first_folio' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $book) {
            if (array_diff(array_keys($book->getDirty()), ['next_number', 'closed_at', 'closing_term', 'updated_at'])) {
                throw ValidationException::withMessages(['book' => 'Os dados de abertura do livro são permanentes.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['book' => 'O livro integra o acervo permanente. Use o encerramento.']));
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function registrations()
    {
        return $this->hasMany(CredentialRegistration::class);
    }

    public function events()
    {
        return $this->hasMany(CredentialRegistryEvent::class);
    }

    public function files()
    {
        return $this->hasMany(CredentialRegistryFile::class);
    }
}
