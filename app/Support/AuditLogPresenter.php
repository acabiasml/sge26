<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CalendarDay;
use App\Models\CalendarEvent;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;

class AuditLogPresenter
{
    public const ACTION_LABELS = [
        'created' => 'Cadastro criado',
        'updated' => 'Cadastro alterado',
        'deleted' => 'Cadastro removido',
    ];

    public const MODEL_LABELS = [
        AcademicPeriod::class => 'Período avaliativo',
        AcademicYear::class => 'Ano letivo',
        Announcement::class => 'Recado',
        CalendarDay::class => 'Dia do calendário',
        CalendarEvent::class => 'Evento',
        IssuedDocument::class => 'Documento emitido',
        Person::class => 'Pessoa',
        PersonContact::class => 'Contato/responsável',
        PersonRelationship::class => 'Relação entre pessoas',
        PersonSchoolRole::class => 'Vínculo',
        School::class => 'Escola',
        User::class => 'Usuário',
    ];

    public const FIELD_LABELS = [
        'active' => 'Situação',
        'address' => 'Endereço',
        'address_complement' => 'Complemento',
        'approved_at' => 'Data de aprovação',
        'birth_date' => 'Data de nascimento',
        'body' => 'Texto',
        'category' => 'Categoria',
        'city' => 'Cidade',
        'class_hour_minutes' => 'Minutos da hora-aula',
        'cnpj' => 'CNPJ',
        'counts_as_school_day' => 'Conta como dia letivo',
        'cpf' => 'CPF',
        'date' => 'Data',
        'description' => 'Descrição',
        'district' => 'Bairro',
        'email' => 'E-mail',
        'ended_at' => 'Fim',
        'ends_at' => 'Fim',
        'founded_at' => 'Data de fundação',
        'full_name' => 'Nome completo',
        'highlight' => 'Destaque',
        'inep' => 'INEP',
        'institutional_email' => 'E-mail institucional',
        'issued_at' => 'Emitido em',
        'legal_name' => 'Razão social',
        'letterhead_text' => 'Texto institucional',
        'logo_path' => 'Logo',
        'minimum_school_days' => 'Mínimo de dias letivos',
        'name' => 'Nome',
        'notes' => 'Observações',
        'number' => 'Número',
        'personal_email' => 'E-mail pessoal',
        'phone' => 'Telefone',
        'position' => 'Função',
        'postal_code' => 'CEP',
        'reference_year' => 'Ano de referência',
        'relationship_type' => 'Relação',
        'role' => 'Papel',
        'school_id' => 'Escola',
        'social_name' => 'Nome social',
        'started_at' => 'Início',
        'starts_at' => 'Início',
        'state' => 'UF',
        'title' => 'Título',
        'type' => 'Tipo',
        'website' => 'Site',
    ];

    public static function actionLabel(?string $action): string
    {
        return self::ACTION_LABELS[$action ?? ''] ?? ($action ?: '-');
    }

    public static function modelLabel(?string $model): string
    {
        return self::MODEL_LABELS[$model ?? ''] ?? class_basename($model ?? '');
    }

    public static function recordLabel(AuditLog $auditLog): string
    {
        return self::modelLabel($auditLog->auditable_type).' #'.$auditLog->auditable_id;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? str($field)->replace('_', ' ')->title()->value();
    }

    /**
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    public static function changes(AuditLog $auditLog): array
    {
        $oldValues = $auditLog->old_values ?? [];
        $newValues = $auditLog->new_values ?? [];
        $keys = collect(array_keys($oldValues))
            ->merge(array_keys($newValues))
            ->unique()
            ->values();

        return $keys->map(fn (string $key): array => [
            'field' => self::fieldLabel($key),
            'old' => $oldValues[$key] ?? null,
            'new' => $newValues[$key] ?? null,
        ])->all();
    }

    public static function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $value;
    }
}
