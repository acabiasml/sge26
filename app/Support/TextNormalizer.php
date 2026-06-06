<?php

namespace App\Support;

class TextNormalizer
{
    /**
     * @var array<int, string>
     */
    private const LOWERCASE_PARTICLES = [
        'da',
        'das',
        'de',
        'do',
        'dos',
        'e',
        'em',
        'na',
        'nas',
        'no',
        'nos',
        'para',
        'por',
        'à',
        'às',
    ];

    /**
     * @var array<int, string>
     */
    private const ACRONYMS = [
        'CPF',
        'CNPJ',
        'CTJJ',
        'EJA',
        'INEP',
        'RG',
        'UF',
    ];

    public static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return $value;
        }

        $words = explode(' ', mb_strtolower($value, 'UTF-8'));

        foreach ($words as $index => $word) {
            $words[$index] = self::normalizeWord($word, $index === 0);
        }

        return implode(' ', $words);
    }

    private static function normalizeWord(string $word, bool $isFirstWord): string
    {
        if (! $isFirstWord && in_array($word, self::LOWERCASE_PARTICLES, true)) {
            return $word;
        }

        $upper = mb_strtoupper($word, 'UTF-8');

        if (in_array($upper, self::ACRONYMS, true)) {
            return $upper;
        }

        $parts = preg_split('/(-)/u', $word, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($parts)) {
            return self::capitalize($word);
        }

        foreach ($parts as $index => $part) {
            if ($part === '-') {
                continue;
            }

            $parts[$index] = self::capitalize($part);
        }

        return implode('', $parts);
    }

    private static function capitalize(string $word): string
    {
        if ($word === '') {
            return $word;
        }

        return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($word, 1, null, 'UTF-8');
    }
}
