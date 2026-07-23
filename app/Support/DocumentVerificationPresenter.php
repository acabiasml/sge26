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

    private static function typeLabel(string $type): string
    {
        return match (true) {
            $type === 'person-record' => 'Ficha de cadastro de pessoa',
            $type === 'school-record' => 'Ficha de cadastro de escola',
            $type === 'academic-calendar' => 'Calendário escolar oficial',
            $type === 'academic-matrices' => 'Matrizes curriculares oficiais',
            $type === 'teacher-diary' => 'Diário de classe',
            $type === 'class-schedule' => 'Horário da turma',
            $type === 'academic-year-schedules' => 'Horários das turmas',
            $type === 'teacher-schedule' => 'Horário docente',
            $type === 'student-schedule' => 'Horário do estudante',
            $type === 'attendance-sheet' => 'Lista de chamada manual',
            $type === 'student-report-card' => 'Boletim escolar',
            $type === 'class-report-cards' => 'Boletins escolares da turma',
            $type === 'class-grade-mirror' => 'Espelho de notas da turma',
            $type === 'student-individual-record' => 'Ficha individual do estudante',
            $type === 'student-academic-history' => 'Histórico escolar do estudante',
            $type === 'class-final-results' => 'Ata de resultados finais da turma',
            $type === 'academic-year-final-results' => 'Resultados finais do ano letivo',
            $type === 'student-enrollment' => 'Ficha de matrícula',
            $type === 'student-enrollment-declaration' => 'Declaração de matrícula',
            $type === 'student-schooling-declaration' => 'Declaração de escolaridade',
            $type === 'student-completion-declaration' => 'Declaração de conclusão',
            $type === 'student-attendance-certificate' => 'Atestado de frequência escolar',
            $type === 'student-transfer-certificate' => 'Atestado de transferência escolar',
            $type === 'data-quality-compliance-report' => 'Relatório de conformidade documental e acadêmica',
            $type === 'official-document' => 'Documento oficial em papel timbrado',
            str_starts_with($type, 'report:') => 'Relatório emitido pelo sistema',
            default => 'Documento emitido pelo sistema',
        };
    }

    private static function description(string $type): string
    {
        return match (true) {
            $type === 'person-record' => 'Este código confirma que uma ficha cadastral de pessoa foi emitida pelo Beabá.',
            $type === 'school-record' => 'Este código confirma que uma ficha cadastral de escola foi emitida pelo Beabá.',
            $type === 'academic-calendar' => 'Este código confirma que um calendário escolar oficial foi emitido pelo Beabá.',
            $type === 'academic-matrices' => 'Este código confirma que um documento oficial de matrizes curriculares foi emitido pelo Beabá.',
            $type === 'teacher-diary' => 'Este código confirma que um diário de classe oficial foi emitido pelo Beabá.',
            $type === 'class-schedule' => 'Este código confirma que um horário oficial de turma foi emitido pelo Beabá.',
            $type === 'academic-year-schedules' => 'Este código confirma que um documento oficial com horários das turmas foi emitido pelo Beabá.',
            $type === 'teacher-schedule' => 'Este código confirma que um horário docente foi emitido pelo Beabá.',
            $type === 'student-schedule' => 'Este código confirma que um horário de estudante foi emitido pelo Beabá.',
            $type === 'attendance-sheet' => 'Este código confirma que uma lista de chamada manual foi emitida pelo Beabá.',
            $type === 'student-report-card' => 'Este código confirma que um boletim escolar foi emitido pelo Beabá.',
            $type === 'class-report-cards' => 'Este código confirma que um conjunto de boletins escolares de uma turma foi emitido pelo Beabá.',
            $type === 'class-grade-mirror' => 'Este código confirma que um espelho de notas de turma foi emitido pelo Beabá.',
            $type === 'student-individual-record' => 'Este código confirma que uma ficha individual de estudante foi emitida pelo Beabá.',
            $type === 'student-academic-history' => 'Este código confirma que um histórico escolar de estudante foi emitido pelo Beabá.',
            $type === 'class-final-results' => 'Este código confirma que uma ata de resultados finais de turma foi emitida pelo Beabá.',
            $type === 'academic-year-final-results' => 'Este código confirma que um documento de resultados finais do ano letivo foi emitido pelo Beabá.',
            $type === 'student-enrollment' => 'Este código confirma que uma ficha física de matrícula foi emitida pelo Beabá.',
            $type === 'student-enrollment-declaration' => 'Este código confirma que uma declaração de matrícula foi emitida pelo Beabá.',
            $type === 'student-schooling-declaration' => 'Este código confirma que uma declaração de escolaridade foi emitida pelo Beabá.',
            $type === 'student-completion-declaration' => 'Este código confirma que uma declaração de conclusão foi emitida pelo Beabá.',
            $type === 'student-attendance-certificate' => 'Este código confirma que um atestado de frequência escolar foi emitido pelo Beabá.',
            $type === 'student-transfer-certificate' => 'Este código confirma que um atestado de transferência escolar foi emitido pelo Beabá.',
            $type === 'data-quality-compliance-report' => 'Este código confirma que um relatório de conferência de conformidade foi emitido pelo Beabá.',
            $type === 'official-document' => 'Este código confirma que um documento oficial em papel timbrado foi emitido pelo Beabá.',
            str_starts_with($type, 'report:') => 'Este código confirma que um relatório administrativo foi emitido pelo Beabá.',
            default => 'Este código confirma que um documento foi emitido pelo Beabá.',
        };
    }
}
