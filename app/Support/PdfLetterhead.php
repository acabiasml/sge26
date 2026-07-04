<?php

namespace App\Support;

use App\Models\School;

class PdfLetterhead
{
    public const MAINTAINER_NAME = 'Centro Técnico Juvenil de Jarudore';
    public const MAINTAINER_CERTIFICATION = 'Certificado CEBAS EDUCAÇÃO, portaria SERES/MEC Nº 269 de 31/07/2023.';
    public const MAINTAINER_CNPJ = '00.176.974/0001-20';
    public const MAINTAINER_EMAIL = 'ctjj.mt@gmail.com';
    public const MAINTAINER_SITE = 'https://ctjj.org/';
    public const MAINTAINER_CORRESPONDENCE_ADDRESS = 'Caixa Postal 338. CEP 78700-970. Rondonópolis-MT.';

    /**
     * @return array{
     *     school: School|null,
     *     maintainer_logo: string|null,
     *     school_logo: string|null,
     *     lines: array<int, string>
     * }
     */
    public static function make(?School $school = null): array
    {
        return [
            'school' => $school,
            'maintainer_logo' => self::imageDataUri('brand/centro-tecnico-juvenil-de-jarudore.png'),
            'school_logo' => self::imageDataUri($school?->logo_path),
            'lines' => $school ? self::schoolLines($school) : self::maintainerLines(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function maintainerLines(): array
    {
        return [
            self::MAINTAINER_NAME,
            self::MAINTAINER_CERTIFICATION,
            'CNPJ: '.self::MAINTAINER_CNPJ.' | Email: '.self::MAINTAINER_EMAIL.' | Site: '.self::MAINTAINER_SITE,
            'Endereço de Correspondência: '.self::MAINTAINER_CORRESPONDENCE_ADDRESS,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function schoolLines(School $school): array
    {
        return collect([
            mb_strtoupper(self::MAINTAINER_NAME),
            self::MAINTAINER_CERTIFICATION,
            mb_strtoupper($school->name),
            self::schoolRegistryLine($school),
            self::schoolContactLine($school),
            self::schoolAddressLine($school),
            $school->letterhead_text,
        ])->filter(fn (?string $line): bool => filled($line))->values()->all();
    }

    private static function schoolRegistryLine(School $school): string
    {
        $parts = collect([
            $school->cnpj ? 'CNPJ: '.$school->cnpj : null,
            $school->inep ? 'INEP: '.$school->inep : null,
            $school->founded_at ? 'Fundação: '.$school->founded_at->translatedFormat('d \d\e M \d\e Y') : null,
        ])->filter();

        return $parts->isEmpty() ? '' : $parts->implode(' | ');
    }

    private static function schoolContactLine(School $school): string
    {
        return collect([
            $school->phone ? 'Tel.: '.$school->phone : null,
            $school->email ? 'E-mail: '.$school->email : null,
            $school->website ? 'Site: '.$school->website : null,
        ])->filter()->implode(' | ');
    }

    private static function schoolAddressLine(School $school): string
    {
        $address = collect([
            $school->address,
            $school->number,
            $school->district,
            collect([$school->city, $school->state])->filter()->join('-'),
            $school->postal_code ? 'CEP '.$school->postal_code : null,
        ])->filter()->join(', ');

        return $address ? 'Endereço: '.$address : '';
    }

    private static function imageDataUri(?string $relativePath): ?string
    {
        if (blank($relativePath)) {
            return null;
        }

        $path = public_path(ltrim($relativePath, '/\\'));

        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
