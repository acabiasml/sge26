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
        'diary_assessment_id' => 'Avaliação',
        'diary_attendance_record_id' => 'Chamada',
        'class_date' => 'Data da aula',
        'mother_name' => 'Nome da mãe',
        'shift' => 'Turno',
        'knowledge_area_id' => 'Área do conhecimento',
        'technical_legal_basis' => 'Base legal do curso técnico',
        'accreditation_act' => 'Ato de credenciamento',
        'authorization_act' => 'Ato de autorização',
        'regulatory_process' => 'Processo de regularização',
        'regulatory_opinion' => 'Parecer de regularização',
        'technological_axis' => 'Eixo tecnológico',
        'offer_forms' => 'Formas de oferta',
        'official_gazette_reference' => 'Publicação no Diário Oficial',
        'authorization_starts_at' => 'Início da autorização',
        'authorization_ends_at' => 'Fim da autorização',
        'module_certifications' => 'Certificações por módulo',
        'stage' => 'Etapa de ensino',
        'weight' => 'Peso da avaliação',
        'ignore_saturdays' => 'Desconsiderar sábados',
        'ignore_sundays' => 'Desconsiderar domingos',
        'id' => 'Referência do registro',
        'academic_year_id' => 'Ano letivo',
        'created_at' => 'Cadastrado em',
        'updated_at' => 'Alterado em',
        'closed_at' => 'Fechado em',
        'closed_by_person_id' => 'Fechado por',
        'closure_notes' => 'Observações de fechamento e reabertura',
        'administrative_reopened_at' => 'Reabertura para alterações administrativas',
        'status' => 'Situação',
        'final_result_status' => 'Resultado final',
        'final_result_details' => 'Detalhes do resultado final',
        'legacy_source' => 'Origem da importação',
        'legacy_id' => 'Referência na origem',
        'legacy_metadata' => 'Informações da importação',
        'weekly_lessons' => 'Aulas por semana',
        'cancelled_at' => 'Cancelado em',
        'cancelled_by_person_id' => 'Cancelado por',
        'enrolled_by_person_id' => 'Matriculado por',
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
        return __(self::ACTION_LABELS[$action ?? ''] ?? ($action ?: '-'));
    }

    public static function modelLabel(?string $model): string
    {
        return __(self::MODEL_LABELS[self::modelClass($model) ?? ''] ?? 'Registro');
    }

    private static function modelClass(?string $type): ?string
    {
        foreach (self::MODEL_LABELS as $class => $label) {
            if ($type === $class || $type === (new $class)->getTable() || $type === class_basename($class)) {
                return $class;
            }
        }

        return null;
    }

    public static function describe(\Illuminate\Database\Eloquent\Model $record, int $depth = 0): string
    {
        $parts = [];
        foreach (['full_name', 'name', 'component_name', 'title'] as $field) {
            if ($record->getAttribute($field)) {
                $parts[] = $record->getAttribute($field);
                break;
            }
        }
        if ($record instanceof AcademicYear && $record->reference_year) {
            $parts[] = $record->reference_year;
        }
        if ($depth < 3) {
            foreach (['person_id', 'teacher_person_id', 'school_class_id', 'academic_year_id', 'student_enrollment_id', 'student_academic_history_id', 'student_academic_history_component_id', 'academic_course_id', 'school_id', 'curriculum_component_id', 'diary_assessment_id', 'diary_attendance_record_id'] as $field) {
                $id = $record->getAttribute($field);
                $class = self::referenceClass($field);
                if ($id && $class && ($related = $class::query()->find($id))) {
                    $description = self::describe($related, $depth + 1);
                    if ($description !== '') {
                        $parts[] = $description;
                    }
                }
            }
        }

        return implode(' · ', array_unique($parts));
    }

    private static function referenceClass(string $field): ?string
    {
        if ($field === 'person_id' || str_ends_with($field, '_person_id')) {
            return Person::class;
        }
        return match ($field) {
            'academic_year_id' => AcademicYear::class,
            'diary_assessment_id' => DiaryAssessment::class,
            'diary_attendance_record_id' => DiaryAttendanceRecord::class,
            'knowledge_area_id' => KnowledgeArea::class,
            'academic_course_id' => AcademicCourse::class,
            'academic_period_id', 'starts_period_id', 'ends_period_id' => AcademicPeriod::class,
            'school_id' => School::class,
            'school_class_id' => SchoolClass::class,
            'student_enrollment_id', 'reclassified_from_enrollment_id' => StudentEnrollment::class,
            'curriculum_component_id' => CurriculumComponent::class,
            'school_class_component_id' => SchoolClassComponent::class,
            'student_academic_history_id' => StudentAcademicHistory::class,
            'student_academic_history_component_id' => StudentAcademicHistoryComponent::class,
            'student_academic_history_year_id' => StudentAcademicHistoryYear::class,
            'issued_by_user_id', 'user_id' => User::class,
            default => null,
        };
    }

    public static function recordLabel(AuditLog $auditLog): string
    {
        $label = self::modelLabel($auditLog->auditable_type);
        if ($auditLog->auditable_type === IssuedDocument::class) {
            $type = $auditLog->new_values['type'] ?? $auditLog->old_values['type'] ?? null;

            if (is_string($type) && $type !== '') {
                $documentLabel = __(DocumentVerificationPresenter::typeLabel($type));
                $personId = $auditLog->new_values['person_id'] ?? $auditLog->old_values['person_id'] ?? null;
                $personName = is_numeric($personId) ? Person::query()->find($personId)?->full_name : null;

                return $personName
                    ? __('screens.issued_document_for_person', ['document' => $documentLabel, 'name' => $personName])
                    : $documentLabel;
            }
        }

        $description = $auditLog->metadata['record_description'] ?? null;
        $class = self::modelClass($auditLog->auditable_type);
        if (! $description && $class) {
            $record = $class::query()->find($auditLog->auditable_id);
            $snapshot = array_merge($record?->getAttributes() ?? [], $auditLog->old_values ?? [], $auditLog->new_values ?? []);
            $description = self::describe((new $class)->forceFill($snapshot));
        }
        if ($description) {
            return $label.' — '.$description;
        }


        if (blank($auditLog->auditable_id)) {
            return $label;
        }

        return __('screens.audit_record_with_id', ['record' => $label, 'id' => $auditLog->auditable_id]);
    }

    public static function fieldLabel(string $field): string
    {
        return __(self::FIELD_LABELS[$field] ?? str($field)->replace('_', ' ')->title()->value());
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
        $model = self::modelClass($model) ?? $model;

        if ($value === null || $value === '') {
            return '-';
        }

        if ($field && is_numeric($value) && ($class = self::referenceClass($field))) {
            $record = $class::query()->find($value);
            return $record ? (self::describe($record) ?: self::modelLabel($class).' #'.$value)
                : __('Registro removido').' ('.__('referência').' '.$value.')';
        }

        if (is_bool($value)) {
            return $value ? __('Sim') : __('Não');
        }

        if (is_string($value) && in_array($field, ['legacy_metadata', 'payload', 'final_result_details', 'offer_forms', 'module_certifications', 'lesson_presence'], true)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($item, $key) => (is_string($key) ? self::fieldLabel($key).': ' : '').self::value($item, is_string($key) ? $key : null))->implode(' · ');
        }

        if ($field === 'type' && is_string($value)) {
            return match ($model) {
                IssuedDocument::class => __(DocumentVerificationPresenter::typeLabel($value)),
                CalendarDay::class => __(CalendarDay::TYPE_LABELS[$value] ?? $value),
                SchoolClassScheduleSlot::class => __(SchoolClassScheduleSlot::TYPE_LABELS[$value] ?? $value),
                StudentEnrollment::class => __(StudentEnrollment::TYPE_LABELS[$value] ?? $value),
                OfficialDocument::class => __(OfficialDocument::TYPE_LABELS[$value] ?? $value),
                default => $value,
            };
        }

        if ($field === 'status' && $model === DiaryAttendanceEntry::class) {
            return __(['present' => 'Presente', 'absent' => 'Ausente', 'justified' => 'Ausência justificada'][$value] ?? $value);
        }
        if ($field === 'final_result_status') {
            return __(StudentEnrollment::FINAL_RESULT_LABELS[$value] ?? $value);
        }
        if ($field === 'position' && $model === PersonSchoolRole::class) {
            return __(PersonSchoolRole::POSITION_LABELS[$value] ?? $value);
        }
        if ($field === 'role') {
            return __(PersonSchoolRole::ROLE_LABELS[$value] ?? $value);
        }
        if ($field === 'status' && $model === StudentEnrollment::class) {
            return __(StudentEnrollment::STATUS_LABELS[$value] ?? $value);
        }
        if ($field === 'transcript_mode') {
            return __(['detailed' => 'Por componente', 'summary' => 'Global', 'no_transcription' => 'Sem transcrição'][$value] ?? $value);
        }
        if ($field && (str_ends_with($field, '_at') || in_array($field, ['date', 'birth_date', 'issued_date', 'class_date'])) && is_string($value)) {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format(str_contains($value, ':') ? 'd/m/Y H:i' : 'd/m/Y');
            } catch (\Throwable) {
                // Preserve a legacy date that cannot be parsed.
            }
        }

        return (string) $value;
    }
}
