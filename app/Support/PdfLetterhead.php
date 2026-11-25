<?php

namespace App\Support;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Illuminate\Support\Str;

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
     *     lines: array<int, string>,
     *     authorization_line: string,
     *     displayed_authorizations: list<string>,
     *     renewal_notices: list<string>,
     *     footer_lines: array<int, string>
     * }
     */
    public static function make(?School $school = null, string|array|null $educationStages = null, ?array $authorization = null): array
    {
        $stages = (array) $educationStages;
        if ($school) {
            $authorization ??= OfficialDocumentCompliance::authorizationDetails($school, $stages);
        }
        $acts = $authorization['acts'] ?? [];
        $automatic = $school?->usesAutomaticLetterhead() ?? false;

        return [
            'school' => $school,
            'maintainer_logo' => self::imageDataUri('brand/centro-tecnico-juvenil-de-jarudore.png'),
            'school_logo' => self::imageDataUri($school?->logo_path),
            'lines' => $school ? self::schoolLines($school, $stages, $automatic) : self::maintainerLines(),
            'authorization_line' => $automatic ? self::authorizationLine($acts) : '',
            'displayed_authorizations' => $automatic ? array_column($acts, 'act') : [],
            'renewal_notices' => $authorization['renewal_notices'] ?? [],
            'footer_lines' => $school ? self::schoolFooterLines($school) : self::maintainerFooterLines(),
        ];
    }

    public static function forCourses(?School $school, iterable $courses, ?int $referenceYear): array
    {
        $courses = collect($courses);

        return self::make(
            $school,
            $courses->pluck('stage')->all(),
            $school ? OfficialDocumentCompliance::courseAuthorizationDetails($school, $courses, $referenceYear) : null,
        );
    }

    public static function forEnrollment(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['courses', 'schoolClass.academicYear.school', 'schoolClass.courses']);
        $year = $enrollment->schoolClass?->academicYear;
        $courses = $enrollment->courses->isNotEmpty() ? $enrollment->courses : ($enrollment->schoolClass?->courses ?? []);

        return self::forCourses($year?->school, $courses, $year?->reference_year);
    }

    public static function forClass(SchoolClass $class): array
    {
        $class->loadMissing(['academicYear.school', 'courses']);
        $year = $class->academicYear;

        return self::forCourses($year?->school, $class->courses, $year?->reference_year);
    }

    /**
     * @return array<int, string>
     */
    private static function maintainerLines(): array
    {
        return [
            self::MAINTAINER_NAME,
            self::MAINTAINER_CERTIFICATION,
            'CNPJ: '.self::MAINTAINER_CNPJ,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function schoolLines(School $school, array $educationStages, bool $automatic): array
    {
        return collect([
            mb_strtoupper($school->maintainer ?: self::MAINTAINER_NAME),
            self::MAINTAINER_CERTIFICATION,
            mb_strtoupper($school->name),
            self::schoolRegistryLine($school),
            ...($automatic ? [] : $school->letterheadTextsForStages($educationStages)),
        ])->filter(fn (?string $line): bool => filled($line))->values()->all();
    }

    private static function authorizationLine(array $acts): string
    {
        $accreditation = self::compactAct($acts['accreditation']['act'] ?? '');
        $authorizationActs = collect($acts)->except('accreditation')->pluck('act')
            ->map(self::compactAct(...))->filter()->unique()->values();

        return collect([
            $accreditation !== '' ? 'Cred.: '.$accreditation : null,
            $authorizationActs->isNotEmpty() ? 'Aut.: '.$authorizationActs->implode(' / ') : null,
        ])->filter()->implode(' · ');
    }

    private static function compactAct(string $act): string
    {
        $act = preg_replace('/\s+(?:[·|;]\s*)?(?:DOE(?:-MT)?\b|Vigência\s*:).*$/iu', '', Str::squish($act));

        return str_ireplace(['Portaria ', 'Resolução ', ' nº '], ['Port. ', 'Res. ', ' '], $act);
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

    /** @return array<int, string> */
    private static function maintainerFooterLines(): array
    {
        return [
            'E-mail: '.self::MAINTAINER_EMAIL.' | Site: '.self::MAINTAINER_SITE,
            'Endereço de correspondência: '.self::MAINTAINER_CORRESPONDENCE_ADDRESS,
        ];
    }

    /** @return array<int, string> */
    private static function schoolFooterLines(School $school): array
    {
        return collect([
            self::schoolContactLine($school),
            self::schoolAddressLine($school),
        ])->filter()->values()->all();
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
