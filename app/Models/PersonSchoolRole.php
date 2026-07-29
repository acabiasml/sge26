<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PersonSchoolRole extends Model
{
    use Auditable;

    public const ROLE_ADMINISTRATOR = 'administrador';
    public const ROLE_MANAGER = 'gestor';
    public const ROLE_TEACHER = 'professor';
    public const ROLE_STUDENT = 'aluno';
    public const ROLE_EMPLOYEE = 'funcionário';

    public const POSITION_DIRECTOR = 'diretor';
    public const POSITION_COORDINATOR = 'coordenador';
    public const POSITION_SECRETARY = 'secretário';

    public const ROLE_PRIORITY = [
        self::ROLE_ADMINISTRATOR => 50,
        self::ROLE_MANAGER => 40,
        self::ROLE_TEACHER => 30,
        self::ROLE_EMPLOYEE => 20,
        self::ROLE_STUDENT => 10,
    ];

    public const ROLE_LABELS = [
        self::ROLE_ADMINISTRATOR => 'Administração',
        self::ROLE_MANAGER => 'Gestão',
        self::ROLE_TEACHER => 'Docência',
        self::ROLE_EMPLOYEE => 'Equipe escolar',
        self::ROLE_STUDENT => 'Estudante',
    ];

    public const POSITION_LABELS = [
        self::POSITION_DIRECTOR => 'Direção',
        self::POSITION_COORDINATOR => 'Coordenação',
        self::POSITION_SECRETARY => 'Secretaria',
    ];

    protected $fillable = [
        'person_id',
        'school_id',
        'role',
        'position',
        'active',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (PersonSchoolRole $role): bool => self::syncPersonActive($role));
        static::deleted(fn (PersonSchoolRole $role): bool => self::syncPersonActive($role));
    }

    private static function syncPersonActive(PersonSchoolRole $role): bool
    {
        return Person::query()
            ->find($role->person_id)
            ?->syncActiveFromRoles() ?? true;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isActiveForDate(mixed $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        if (! $this->active) {
            return false;
        }

        if ($this->started_at && $this->started_at->toDateString() > $date) {
            return false;
        }

        if ($this->ended_at && $this->ended_at->toDateString() < $date) {
            return false;
        }

        return true;
    }

    public static function activeAdministratorCount(): int
    {
        return self::activeAdministratorQuery()->count();
    }

    /**
     * @return Builder<PersonSchoolRole>
     */
    public static function activeAdministratorQuery(): Builder
    {
        return self::query()
            ->where('role', self::ROLE_ADMINISTRATOR)
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

    public function isLastActiveAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR
            && $this->isActiveForDate()
            && self::activeAdministratorCount() <= 1;
    }

    public function label(): string
    {
        $label = self::ROLE_LABELS[$this->role] ?? $this->role;

        if ($this->role === self::ROLE_MANAGER && $this->position) {
            return $label.' - '.$this->positionLabel();
        }

        return $label;
    }

    public function positionLabel(): string
    {
        return self::POSITION_LABELS[$this->position] ?? $this->position ?? '';
    }
}
