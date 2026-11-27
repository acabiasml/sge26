<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OfficialDocumentCompliance
{
    public static function courseAuthorizationDetails(School $school, iterable $courses, ?int $referenceYear): array
    {
        $courses = collect($courses);
        $offerings = $courses->map(fn (AcademicCourse $course) => [$course->stage, $course->modality, $referenceYear, $course]);

        return self::authorizationDetails($school, $courses->pluck('stage')->all(), $offerings->all());
    }

    public static function authorizationMessage(School $school, array $stages): ?string
    {
        return self::authorizationDetails($school, $stages)['message'];
    }

    /**
     * @return array{acts: array, renewal_notices: list<string>, message: ?string}
     */
    public static function authorizationDetails(School $school, array $stages, array $offerings = []): array
    {
        $date = now('America/Cuiaba')->toDateString();
        $acts = $school->document_authorizations ?? [];
        $keys = ['accreditation'];
        $stagesWithoutModality = array_fill_keys(array_intersect($stages, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL]), true);
        $isLar = preg_replace('/\D/', '', (string) $school->cnpj) === '00176974000392';
        foreach ($offerings as $offering) {
            [$stage, $modality, $referenceYear] = $offering;
            if (! isset(AcademicCourse::STAGE_LABELS[$stage]) || $stage === AcademicCourse::STAGE_OTHER) {
                continue;
            }
            if ($stage === AcademicCourse::STAGE_TECHNICAL && self::hasCurrentTechnicalAuthorization($offering[3] ?? null, $date)) {
                continue;
            }
            $modality = self::modalityKey($modality);
            // A matriz de 2024 permanece como registrada. Para emissão, use o ato atual da oferta.
            if ($isLar && $stage === AcademicCourse::STAGE_ELEMENTARY && $modality === AcademicCourse::MODALITY_REGULAR
                && (int) $referenceYear === 2024 && ! self::isCurrentAct($acts['fundamental_regular'] ?? [], $date)) {
                $modality = AcademicCourse::MODALITY_RURAL;
            }
            $keys[] = $stage.'_'.($modality ?? 'missing');
            unset($stagesWithoutModality[$stage]);
        }
        foreach (array_keys($stagesWithoutModality) as $stage) {
            array_push($keys, ...self::stageAuthorizationKeys($acts, $stage, $date));
        }

        $details = ['acts' => [], 'renewal_notices' => [], 'message' => null];
        $labels = School::documentAuthorizationLabels();
        foreach (array_unique($keys) as $key) {
            $act = $acts[$key] ?? [];
            if (self::isCurrentAct($act, $date)) {
                $details['acts'][$key] = $act;

                continue;
            }
            if ($school->state !== 'MT') {
                continue;
            }
            if ($notice = self::renewalNotice($key, $act, $date)) {
                $details['renewal_notices'][] = $notice;

                continue;
            }

            $details['message'] ??= isset($labels[$key])
                ? __('Confira o ato, a publicação e a vigência em Atos autorizativos no cadastro da escola. Pendência: :authorization.', [
                    'authorization' => __($labels[$key]),
                ])
                : __('Identifique a modalidade da oferta no cadastro da matriz ou do histórico antes de emitir.');
        }
        $details['renewal_notices'] = array_values(array_unique($details['renewal_notices']));

        return $details;
    }

    private static function stageAuthorizationKeys(array $acts, string $stage, string $date): array
    {
        $current = [];
        $renewing = [];
        foreach ($acts as $key => $act) {
            if (! str_starts_with($key, $stage.'_') || ! is_array($act)) {
                continue;
            }
            if (self::isCurrentAct($act, $date)) {
                $current[] = $key;
            } elseif (self::renewalNotice($key, $act, $date) !== null) {
                $renewing[] = $key;
            }
        }

        return $current ?: ($renewing ?: [$stage]);
    }

    public static function isCurrentAct(array $act, string $date): bool
    {
        return filled($act['act'] ?? null) && filled($act['published_on'] ?? null) && filled($act['starts_on'] ?? null)
            && $act['published_on'] <= $date && $act['starts_on'] <= $date && (blank($act['ends_on'] ?? null) || $act['ends_on'] >= $date);
    }

    private static function renewalNotice(string $key, array $act, string $date): ?string
    {
        $expires = $act['ends_on'] ?? null;
        $filed = $act['renewal_filed_on'] ?? null;
        $protocol = $act['renewal_protocol'] ?? null;
        if (! School::supportsDocumentAuthorizationRenewal($key)
            || blank($expires) || $expires >= $date || ! self::isCurrentAct($act, $expires)
            || blank($filed) || $filed < $act['starts_on'] || $filed > $date || blank($protocol)) {
            return null;
        }

        return __('Autorização em renovação no CEE-MT (SIPE nº :protocol, protocolado em :filed). Último ato: :act. Vigência encerrada em :expires.', [
            'protocol' => $protocol,
            'filed' => CarbonImmutable::parse($filed)->format('d/m/Y'),
            'act' => $act['act'],
            'expires' => CarbonImmutable::parse($expires)->format('d/m/Y'),
        ]);
    }

    private static function hasCurrentTechnicalAuthorization(?AcademicCourse $course, string $date): bool
    {
        return $course && filled($course->authorization_act) && filled($course->official_gazette_reference)
            && $course->authorization_starts_at && $course->authorization_ends_at
            && $course->authorization_starts_at->toDateString() <= $date && $course->authorization_ends_at->toDateString() >= $date;
    }

    private static function modalityKey(?string $modality): ?string
    {
        $normalized = Str::of((string) $modality)->ascii()->lower()->squish()->toString();
        if (isset(AcademicCourse::MODALITY_LABELS[$normalized])) {
            return $normalized;
        }
        if ($normalized === 'ept') {
            return AcademicCourse::MODALITY_PROFESSIONAL_TECHNOLOGICAL;
        }
        foreach (AcademicCourse::MODALITY_LABELS as $key => $label) {
            if ($normalized === Str::lower(Str::ascii($label))) {
                return $key;
            }
        }

        return null;
    }

    public static function historyIdentityMessage(Person $student): ?string
    {
        if (blank($student->schoolPhotoIdentification()) || blank($student->nationality) || blank($student->birth_city)
            || blank($student->birth_state) || ! $student->birth_date) {
            return __('Confira a identificação com foto, a nacionalidade e os dados de nascimento no cadastro da pessoa.');
        }
        $age = $student->birth_date->age;
        if ($age >= 18 && $age < 70 && Str::contains(Str::lower(Str::ascii($student->nationality)), 'brasil')
            && blank($student->school_voter_registration) && blank($student->school_voter_exemption)) {
            return __('Informe o título de eleitor ou o motivo da dispensa na ficha do estudante.');
        }

        return null;
    }

    public static function schoolMessage(School $school): ?string
    {
        $missing = $school->missingOfficialDocumentFields();

        if ($missing === []) {
            return null;
        }

        return __('Documento bloqueado: complete o cadastro oficial da escola antes de emitir. Campos pendentes: ')
            .implode(', ', array_map(fn (string $field): string => __($field), $missing))
            .__('. Abra o cadastro da escola e revise os dados do papel timbrado.');
    }

    public static function personMessage(Person $person): ?string
    {
        $missing = $person->missingSchoolDocumentFields();

        if ($missing === []) {
            return null;
        }

        return __('Documento bloqueado: complete o cadastro da pessoa antes de emitir. Campos pendentes: ')
            .implode(', ', array_map(fn (string $field): string => __($field), $missing))
            .__('. Abra a ficha da pessoa e revise os dados civis e de endereço.');
    }

    public static function studentMessage(Person $student, bool $missingCpfConfirmed = false): ?string
    {
        $missing = array_values(array_filter(
            $student->missingSchoolDocumentFields(),
            fn (string $field): bool => $field !== 'CPF'
        ));

        if ($missing !== []) {
            return __('Documento bloqueado: complete o cadastro do estudante antes de emitir. Campos pendentes: ')
                .implode(', ', array_map(fn (string $field): string => __($field), $missing)).'.';
        }

        if (self::studentHasNoCpf($student) && ! self::hasParentCpf($student)) {
            return __('Documento bloqueado: cadastre o CPF da mãe ou do pai na seção Responsáveis e contatos.');
        }

        if (self::studentHasNoCpf($student) && ! $missingCpfConfirmed) {
            return __('Confirme que o documento será emitido sem o CPF do estudante.');
        }

        return null;
    }

    public static function studentHasNoCpf(Person $student): bool
    {
        return strlen((string) preg_replace('/\D+/', '', (string) $student->cpf)) !== 11;
    }

    public static function hasParentCpf(Person $student): bool
    {
        $student->loadMissing('contacts');

        return $student->contacts
            ->whereIn('relationship_type', [PersonContact::TYPE_MOTHER, PersonContact::TYPE_FATHER])
            ->contains(fn (PersonContact $contact): bool => strlen((string) preg_replace('/\D+/', '', (string) $contact->cpf)) === 11);
    }
}
