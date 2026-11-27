<?php

namespace App\Support;

use Illuminate\Support\Str;

enum SchoolGender: string
{
    case Masculine = 'Masculino';
    case Feminine = 'Feminino';

    public static function fromRecordedValue(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return match (Str::of($value)->ascii()->lower()->trim()->rtrim('.')->toString()) {
            'm', 'mas', 'masc', 'masculino', 'male' => self::Masculine,
            'f', 'fem', 'feminino', 'female' => self::Feminine,
            default => null,
        };
    }
}
