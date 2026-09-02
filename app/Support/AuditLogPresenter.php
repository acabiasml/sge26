<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodDiaryConsolidation;
use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\CurriculumComponentSubstitution;
use App\Models\DiaryAlert;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryPeriodConfirmation;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\OfficialDocument;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolAcademicCriteria;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassComponentSubstitution;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassScheduleSlot;
use App\Models\SchoolConcept;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentBehaviorGrade;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use App\Models\User;

class AuditLogPresenter
{
    public const ACTION_LABELS = [
        'created' => 'Cadastro criado',
        'updated' => 'Cadastro alterado',
        'deleted' => 'Cadastro removido',
    ];

    public const MODEL_LABELS = [
        AcademicCourse::class => 'Matriz curricular',
        AcademicPeriod::class => 'Período avaliativo',
        AcademicPeriodDiaryConsolidation::class => 'Consolidação de período avaliativo',
        AcademicYear::class => 'Ano letivo',
        Announcement::class => 'Recado',
        CalendarDay::class => 'Dia do calendário',
        CurriculumComponent::class => 'Componente curricular',
        CurriculumComponentSubstitution::class => 'Substituição docente',
        DiaryAlert::class => 'Alerta do diário',
        DiaryAttendanceEntry::class => 'Registro de presença',
        DiaryAttendanceJustification::class => 'Justificativa de ausência',
        DiaryAttendanceRecord::class => 'Chamada',
        DiaryAssessment::class => 'Avaliação do diário',
        DiaryAssessmentResult::class => 'Nota de avaliação',
        DiaryContent::class => 'Conteúdo do diário',
        DiaryPeriodConfirmation::class => 'Confirmação de diário',
        IssuedDocument::class => 'Documento emitido',
        KnowledgeArea::class => 'Área do conhecimento',
        OfficialDocument::class => 'Documento oficial',
        Person::class => 'Pessoa',
        PersonContact::class => 'Contato/responsável',
        PersonRelationship::class => 'Relação entre pessoas',
        PersonSchoolRole::class => 'Vínculo',
        School::class => 'Escola',
        SchoolAcademicCriteria::class => 'Critério acadêmico',
        SchoolAssessmentRule::class => 'Configuração de avaliações',
        SchoolClass::class => 'Turma',
        SchoolClassComponent::class => 'Docência da turma',
        SchoolClassComponentSubstitution::class => 'Substituição docente da turma',
        SchoolClassSchedule::class => 'Horário da turma',
        SchoolClassScheduleSlot::class => 'Bloco do horário',
        SchoolConcept::class => 'Conceito avaliativo',
        StudentAcademicHistory::class => 'Histórico escolar',
        StudentAcademicHistoryComponent::class => 'Componente do histórico escolar',
        StudentAcademicHistoryRecord::class => 'Resultado do histórico escolar',
        StudentAcademicHistoryYear::class => 'Ano do histórico escolar',
        StudentBehaviorGrade::class => 'Nota de comportamento',
        StudentEnrollment::class => 'Matrícula',
        StudentPeriodConvalidation::class => 'Convalidação de nota',
        User::class => 'Usuário',
    ];

    public const FIELD_LABELS = [
        'academic_course_id' => 'Matriz',
        'academic_period_id' => 'Período avaliativo',
        'active' => 'Situação',
        'address' => 'Endereço',
        'address_complement' => 'Complemento',
        'abbreviation' => 'Abreviatura',
        'approved_at' => 'Data de aprovação',
        'assessment_date' => 'Data da avaliação',
        'attendance_label' => 'Frequência informada',
        'birth_date' => 'Data de nascimento',
        'body' => 'Texto',
        'category' => 'Categoria',
        'city' => 'Cidade',
        'class_hour_minutes' => 'Minutos da hora-aula',
        'cnpj' => 'CNPJ',
        'component_name' => 'Componente curricular',
        'counts_as_school_day' => 'Conta como dia letivo',
        'consolidated' => 'Consolidado',
        'consolidated_at' => 'Consolidado em',
        'consolidated_by_person_id' => 'Consolidado por',
        'confirmed' => 'Confirmado',
        'confirmed_at' => 'Confirmado em',
        'confirmed_by_person_id' => 'Confirmado por',
        'content' => 'Conteúdo ministrado',
        'country' => 'País',
        'cpf' => 'CPF',
        'created_by_person_id' => 'Criado por',
        'curriculum_component_id' => 'Componente curricular',
        'date' => 'Data',
        'description' => 'Descrição',
        'dismissed_at' => 'Dispensado em',
        'attended_lessons' => 'Aulas com presença',
        'district' => 'Bairro',
        'email' => 'E-mail',
        'ended_at' => 'Fim',
        'ends_at' => 'Fim',
        'allow_diary_entries_outside_period' => 'Lançamentos de diário fora do período',
        'ends_period_id' => 'Período final',
        'enrolled_at' => 'Data de matrícula',
        'final_result' => 'Resultado final',
        'formation' => 'Formação',
        'founded_at' => 'Data de fundação',
        'frequency_label' => 'Frequência informada',
        'frequency_percentage' => 'Percentual de frequência',
        'full_name' => 'Nome completo',
        'grade_phase' => 'Ano/série/etapa',
        'highlight' => 'Destaque',
        'inep' => 'INEP',
        'institutional_email' => 'E-mail institucional',
        'issued_at' => 'Emitido em',
        'issued_by_user_id' => 'Emitido pelo usuário',
        'issued_date' => 'Data de emissão',
        'issued_place' => 'Local de emissão',
        'is_recovery' => 'É recuperação',
        'itinerary_name' => 'Nome do itinerário formativo',
        'knowledge_area' => 'Área do conhecimento',
        'label' => 'Rótulo',
        'lesson_count' => 'Quantidade de aulas',
        'lesson_presence' => 'Presença por aula',
        'legal_name' => 'Razão social',
        'legal_basis' => 'Base legal',
        'letterhead_text' => 'Texto institucional',
        'logo_path' => 'Logo',
        'maximum_inclusive' => 'Inclui nota máxima',
        'maximum_score' => 'Nota máxima',
        'message' => 'Mensagem',
        'minimum_inclusive' => 'Inclui nota mínima',
        'minimum_score' => 'Nota mínima',
        'minimum_school_days' => 'Referência de dias letivos',
        'minimum_attendance_percentage' => 'Frequência mínima para aprovação',
        'nis' => 'NIS',
        'receives_federal_aid' => 'Recebe auxílio do Governo Federal',
        'modality' => 'Modalidade',
        'name' => 'Nome',
        'notes' => 'Observações',
        'number' => 'Número',
        'personal_email' => 'E-mail pessoal',
        'person_id' => 'Pessoa',
        'phone' => 'Telefone',
        'passing_score' => 'Média mínima para aprovação',
        'passing_points' => 'Soma mínima de pontos para aprovação',
        'position' => 'Função',
        'payload' => 'Dados complementares',
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
        'resolved_at' => 'Resolvido em',
        'role' => 'Papel',
        'school_class_id' => 'Turma',
        'school_class_component_id' => 'Componente da turma',
        'school_id' => 'Escola',
        'school_days' => 'Dias letivos',
        'school_name' => 'Nome da escola',
        'score' => 'Nota',
        'score_label' => 'Nota informada',
        'score_numeric' => 'Nota numérica',
        'social_name' => 'Nome social',
        'sort_order' => 'Ordem',
        'started_at' => 'Início',
        'starts_at' => 'Início',
        'starts_period_id' => 'Período inicial',
        'state' => 'UF',
        'student_academic_history_component_id' => 'Componente do histórico escolar',
        'student_academic_history_id' => 'Histórico escolar',
        'student_academic_history_year_id' => 'Ano do histórico escolar',
        'student_enrollment_id' => 'Matrícula',
        'substitute_teacher_person_id' => 'Docência substituta',
        'teacher_person_id' => 'Docência titular',
        'title' => 'Título',
        'transcript_mode' => 'Formato do histórico',
        'transferred_at' => 'Data de transferência',
        'transferred_by_person_id' => 'Transferido por',
        'type' => 'Tipo',
        'updated_by_person_id' => 'Última alteração por',
        'uuid' => 'Identificador único',
        'verification_code' => 'Código de verificação',
        'weekday' => 'Dia da semana',
        'website' => 'Site',
        'workload_hours' => 'Carga horária',
        'year' => 'Ano',
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
        $label = self::modelLabel($auditLog->auditable_type);

        if ($auditLog->auditable_type === IssuedDocument::class) {
            $type = $auditLog->new_values['type'] ?? $auditLog->old_values['type'] ?? null;

            if (is_string($type) && $type !== '') {
                $label .= ' — tipo: '.DocumentVerificationPresenter::typeLabel($type);
            }
        }

        if (blank($auditLog->auditable_id)) {
            return $label;
        }

        return $label.' (registro '.$auditLog->auditable_id.')';
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? str($field)->replace('_', ' ')->title()->value();
    }

    /**
     * @return array<int, array{key: string, field: string, old: mixed, new: mixed}>
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
            'key' => $key,
            'field' => self::fieldLabel($key),
            'old' => $oldValues[$key] ?? null,
            'new' => $newValues[$key] ?? null,
        ])->all();
    }

    public static function value(mixed $value, ?string $field = null, ?string $model = null): string
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

        if ($field === 'type' && is_string($value)) {
            return match ($model) {
                IssuedDocument::class => DocumentVerificationPresenter::typeLabel($value),
                CalendarDay::class => CalendarDay::TYPE_LABELS[$value] ?? $value,
                SchoolClassScheduleSlot::class => SchoolClassScheduleSlot::TYPE_LABELS[$value] ?? $value,
                StudentEnrollment::class => StudentEnrollment::TYPE_LABELS[$value] ?? $value,
                OfficialDocument::class => OfficialDocument::TYPE_LABELS[$value] ?? $value,
                default => $value,
            };
        }

        return (string) $value;
    }
}
