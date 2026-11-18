<?php

namespace App\Support;

use App\Models\FreeCourseOffering;
use App\Models\FreeCourseParticipant;
use App\Models\Person;

final class FreeCourseCertificate
{
    public function version(FreeCourseOffering $offering): string
    {
        return hash('sha256', json_encode([$offering->freeCourse->getAttributes(), $offering->freeCourse->school->getAttributes(), $offering->getAttributes(),
            $offering->participants()->with('person')->orderBy('id')->get()->map(fn ($p) => [$p->getAttributes(), $p->person->only(['full_name', 'cpf', 'birth_date', 'birth_city', 'birth_state', 'nationality'])])->all()], JSON_THROW_ON_ERROR));
    }

    public function missingIdentity(Person $person): array
    {
        $labels = ['cpf' => 'CPF', 'birth_date' => 'data de nascimento', 'birth_city' => 'naturalidade', 'birth_state' => 'UF da naturalidade', 'nationality' => 'nacionalidade'];
        $missing = collect($labels)->filter(fn ($label, $field) => blank($person->{$field}))->values()->all();
        if (filled($person->cpf) && strlen(preg_replace('/\D/', '', $person->cpf)) !== 11) {
            $missing[] = 'CPF com 11 dígitos';
        }
        if (! self::fullName($person->full_name)) {
            $missing[] = 'nome completo, sem abreviações';
        }

        return $missing;
    }

    public function eligible(FreeCourseParticipant $participant): bool
    {
        $today = now('America/Cuiaba')->toDateString();

        return ! $participant->removed_at && $participant->completed_at && $participant->completed_at->toDateString() <= $today
            && $participant->offering->ends_at->toDateString() <= $today
            && $participant->completed_at->gte($participant->offering->ends_at);
    }

    public static function fullName(string $value): bool
    {
        return count(preg_split('/\s+/u', trim($value))) >= 2 && ! str_contains($value, '.')
            && ! preg_match('/(?:^|\s)[\p{L}]\.?(?=\s|$)/u', preg_replace('/\b[eE]\b/u', 'e-e', $value));
    }

    /** Divide o conteúdo por linhas estimadas, preservando os parágrafos e palavras. */
    public static function syllabusPages(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph)) as $word) {
                if ($line !== '' && mb_strlen($line.' '.$word) > 105) {
                    $lines[] = $line;
                    $line = '';
                }
                while (mb_strlen($word) > 105) {
                    if ($line !== '') {
                        $lines[] = $line;
                        $line = '';
                    }
                    $lines[] = mb_substr($word, 0, 105);
                    $word = mb_substr($word, 105);
                }
                $line = trim($line.' '.$word);
            }
            $lines[] = $line;
        }

        return array_chunk($lines, 18);
    }
}
