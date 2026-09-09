<?php

namespace App\Support;

use App\Models\IssuedDocument;

class DocumentVerificationPresenter
{
    /**
     * @return array{
     *     type_label: string,
     *     description: string,
     *     title: string|null,
     *     scope_label: string|null,
     *     rows_count: int|null,
     *     school_name: string|null,
     *     revoked: bool
     * }
     */
    public static function make(IssuedDocument $document): array
    {
        return [
            'type_label' => self::typeLabel($document->type),
            'description' => self::description($document->type),
            'title' => is_string($document->payload['title'] ?? null) ? $document->payload['title'] : null,
            'scope_label' => is_string($document->payload['scope_label'] ?? null) ? $document->payload['scope_label'] : null,
            'rows_count' => is_numeric($document->payload['rows_count'] ?? null) ? (int) $document->payload['rows_count'] : null,
            'school_name' => $document->school?->name,
            'revoked' => $document->revoked_at !== null,
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match (true) {
            $type === 'person-record' => __('Ficha de cadastro de pessoa'),
            $type === 'school-record' => __('Ficha de cadastro de escola'),
            $type === 'academic-calendar' => __('Calendário escolar oficial'),
            $type === 'academic-matrices' => __('Matrizes curriculares oficiais'),
            $type === 'teacher-diary' => __('Diário de classe'),
            $type === 'class-schedule' => __('Horário da turma'),
            $type === 'academic-year-schedules' => __('Horários das turmas'),
            $type === 'teacher-schedule' => __('Horário docente'),
            $type === 'student-schedule' => __('Horário do estudante'),
            $type === 'attendance-sheet' => __('Lista de chamada manual'),
            $type === 'student-report-card' => __('Boletim escolar'),
            $type === 'class-report-cards' => __('Boletins escolares da turma'),
            $type === 'class-grade-mirror' => __('Espelho de notas da turma'),
            $type === 'student-individual-record' => __('Ficha individual do estudante'),
            $type === 'student-academic-history' => __('Histórico escolar do estudante'),
            $type === 'class-final-results' => __('Ata de resultados finais da turma'),
            $type === 'academic-year-final-results' => __('Resultados finais do ano letivo'),
            $type === 'student-enrollment' => __('Ficha de matrícula'),
            $type === 'student-enrollment-declaration' => __('Declaração de matrícula'),
            $type === 'student-schooling-declaration' => __('Declaração de escolaridade'),
            $type === 'student-completion-declaration' => __('Declaração de conclusão'),
            $type === 'student-attendance-certificate' => __('Atestado de frequência escolar'),
            $type === 'student-transfer-certificate' => __('Atestado de transferência escolar'),
            $type === 'data-quality-compliance-report' => __('Relatório de conformidade documental e acadêmica'),
            $type === 'official-document' => __('Documento oficial em papel timbrado'),
            str_starts_with($type, 'report:') => __('Relatório emitido pelo sistema'),
            default => __('Documento emitido pelo sistema'),
        };
    }

    private static function description(string $type): string
    {
        return match (true) {
            $type === 'person-record' => __('Este código confirma que uma ficha cadastral de pessoa foi emitida pelo Beabá.'),
            $type === 'school-record' => __('Este código confirma que uma ficha cadastral de escola foi emitida pelo Beabá.'),
            $type === 'academic-calendar' => __('Este código confirma que um calendário escolar oficial foi emitido pelo Beabá.'),
            $type === 'academic-matrices' => __('Este código confirma que um documento oficial de matrizes curriculares foi emitido pelo Beabá.'),
            $type === 'teacher-diary' => __('Este código confirma que um diário de classe oficial foi emitido pelo Beabá.'),
            $type === 'class-schedule' => __('Este código confirma que um horário oficial de turma foi emitido pelo Beabá.'),
            $type === 'academic-year-schedules' => __('Este código confirma que um documento oficial com horários das turmas foi emitido pelo Beabá.'),
            $type === 'teacher-schedule' => __('Este código confirma que um horário docente foi emitido pelo Beabá.'),
            $type === 'student-schedule' => __('Este código confirma que um horário de estudante foi emitido pelo Beabá.'),
            $type === 'attendance-sheet' => __('Este código confirma que uma lista de chamada manual foi emitida pelo Beabá.'),
            $type === 'student-report-card' => __('Este código confirma que um boletim escolar foi emitido pelo Beabá.'),
            $type === 'class-report-cards' => __('Este código confirma que um conjunto de boletins escolares de uma turma foi emitido pelo Beabá.'),
            $type === 'class-grade-mirror' => __('Este código confirma que um espelho de notas de turma foi emitido pelo Beabá.'),
            $type === 'student-individual-record' => __('Este código confirma que uma ficha individual de estudante foi emitida pelo Beabá.'),
            $type === 'student-academic-history' => __('Este código confirma que um histórico escolar de estudante foi emitido pelo Beabá.'),
            $type === 'class-final-results' => __('Este código confirma que uma ata de resultados finais de turma foi emitida pelo Beabá.'),
            $type === 'academic-year-final-results' => __('Este código confirma que um documento de resultados finais do ano letivo foi emitido pelo Beabá.'),
            $type === 'student-enrollment' => __('Este código confirma que uma ficha física de matrícula foi emitida pelo Beabá.'),
            $type === 'student-enrollment-declaration' => __('Este código confirma que uma declaração de matrícula foi emitida pelo Beabá.'),
            $type === 'student-schooling-declaration' => __('Este código confirma que uma declaração de escolaridade foi emitida pelo Beabá.'),
            $type === 'student-completion-declaration' => __('Este código confirma que uma declaração de conclusão foi emitida pelo Beabá.'),
            $type === 'student-attendance-certificate' => __('Este código confirma que um atestado de frequência escolar foi emitido pelo Beabá.'),
            $type === 'student-transfer-certificate' => __('Este código confirma que um atestado de transferência escolar foi emitido pelo Beabá.'),
            $type === 'data-quality-compliance-report' => __('Este código confirma que um relatório de conferência de conformidade foi emitido pelo Beabá.'),
            $type === 'official-document' => __('Este código confirma que um documento oficial em papel timbrado foi emitido pelo Beabá.'),
            str_starts_with($type, 'report:') => __('Este código confirma que um relatório administrativo foi emitido pelo Beabá.'),
            default => __('Este código confirma que um documento foi emitido pelo Beabá.'),
        };
    }
}
