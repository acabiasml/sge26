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
            str_starts_with($type, 'report:') => 'Este código confirma que um relatório administrativo foi emitido pelo Beabá.',
            default => 'Este código confirma que um documento foi emitido pelo Beabá.',
        };
    }
}
