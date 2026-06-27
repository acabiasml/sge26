<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\CurriculumComponentSubstitution;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryAssessment;
use App\Models\DiaryContent;
use App\Models\DiaryPeriodConfirmation;
use App\Models\SchoolAssessmentRule;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassComponentSubstitution;
use App\Models\StudentEnrollment;
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
        CurriculumComponent::class => 'Componente curricular',
        CurriculumComponentSubstitution::class => 'Substituição docente',
        DiaryAttendanceEntry::class => 'Registro de presença',
        DiaryAttendanceJustification::class => 'Justificativa de ausência',
        DiaryAttendanceRecord::class => 'Chamada',
        DiaryAssessment::class => 'Avaliação do diário',
        DiaryContent::class => 'Conteúdo do diário',
        DiaryPeriodConfirmation::class => 'Confirmação de diário',
        SchoolAssessmentRule::class => 'Configuração de avaliações',
        IssuedDocument::class => 'Documento emitido',
        Person::class => 'Pessoa',
        PersonContact::class => 'Contato/responsável',
        PersonRelationship::class => 'Relação entre pessoas',
        PersonSchoolRole::class => 'Vínculo',
        School::class => 'Escola',
        SchoolClass::class => 'Turma',
        SchoolClassComponent::class => 'Docência da turma',
        SchoolClassComponentSubstitution::class => 'Substituição docente da turma',
        StudentEnrollment::class => 'Matrícula',
        User::class => 'Usuário',
    ];

    public const FIELD_LABELS = [
        'academic_course_id' => 'Matriz',
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
        'confirmed' => 'Confirmado',
        'confirmed_at' => 'Confirmado em',
        'confirmed_by_person_id' => 'Confirmado por',
        'content' => 'Conteúdo ministrado',
        'cpf' => 'CPF',
        'curriculum_component_id' => 'Componente curricular',
        'date' => 'Data',
        'description' => 'Descrição',
        'attended_lessons' => 'Aulas com presença',
        'district' => 'Bairro',
        'email' => 'E-mail',
        'ended_at' => 'Fim',
        'ends_at' => 'Fim',
        'enrolled_at' => 'Data de matrícula',
        'founded_at' => 'Data de fundação',
        'full_name' => 'Nome completo',
        'highlight' => 'Destaque',
        'inep' => 'INEP',
        'institutional_email' => 'E-mail institucional',
        'issued_at' => 'Emitido em',
        'is_recovery' => 'É recuperação',
        'lesson_count' => 'Quantidade de aulas',
        'lesson_presence' => 'Presença por aula',
        'legal_name' => 'Razão social',
        'letterhead_text' => 'Texto institucional',
        'logo_path' => 'Logo',
        'minimum_school_days' => 'Mínimo de dias letivos',
        'minimum_attendance_percentage' => 'Frequência mínima para aprovação',
        'name' => 'Nome',
        'notes' => 'Observações',
        'number' => 'Número',
        'personal_email' => 'E-mail pessoal',
        'phone' => 'Telefone',
        'passing_score' => 'Média mínima para aprovação',
        'passing_points' => 'Soma mínima de pontos para aprovação',
        'position' => 'Função',
        'postal_code' => 'CEP',
        'reclassified_at' => 'Data de reclassificação',
        'reclassified_by_person_id' => 'Reclassificado por',
        'reclassified_from_enrollment_id' => 'Matrícula de origem',
        'reference_year' => 'Ano de referência',
        'recovery_mode' => 'Modalidade de recuperação',
        'recovery_replaced_rule_id' => 'Avaliação substituída pela recuperação',
        'recovery_weight' => 'Peso da recuperação',
        'reopen_reason' => 'Motivo da reabertura',
        'reopened_at' => 'Reaberto em',
        'reopened_by_person_id' => 'Reaberto por',
        'relationship_type' => 'Relação',
        'role' => 'Papel',
        'school_class_id' => 'Turma',
        'school_id' => 'Escola',
        'social_name' => 'Nome social',
        'started_at' => 'Início',
        'starts_at' => 'Início',
        'state' => 'UF',
        'substitute_teacher_person_id' => 'Docência substituta',
        'title' => 'Título',
        'transferred_at' => 'Data de transferência',
        'transferred_by_person_id' => 'Transferido por',
        'type' => 'Tipo',
        'updated_by_person_id' => 'Última alteração por',
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
