<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\School;
use Illuminate\Support\Str;

class OfficialDocumentCompliance
{
    public static function courseAuthorizationMessage(School $school, iterable $courses): ?string
    {
        $offerings = collect($courses)->map(fn (AcademicCourse $course) => [$course->stage, $course->modality]);

        return self::authorizationMessage($school, $offerings->pluck(0)->all(), $offerings->all());
    }

    public static function authorizationKeys(array $stages, array $offerings): array
    {
        $keys = ['accreditation', ...array_intersect($stages, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL])];
        foreach ($offerings as [$stage, $modality]) {
            if (isset(AcademicCourse::STAGE_LABELS[$stage]) && $stage !== AcademicCourse::STAGE_OTHER) {
                $keys[] = $stage.'_'.(self::modalityKey($modality) ?? 'missing');
            }
        }

        return array_unique($keys);
    }

    public static function authorizationMessage(School $school, array $stages, array $offerings = []): ?string
    {
        if ($school->state !== 'MT') {
            return null;
        }
        $date = now('America/Cuiaba')->toDateString();
        $labels = School::documentAuthorizationLabels();
        foreach (self::authorizationKeys($stages, $offerings) as $key) {
            if (! isset($labels[$key])) {
                return __('Identifique a modalidade da oferta no cadastro da matriz ou do histórico antes de emitir.');
            }
            $act = $school->document_authorizations[$key] ?? [];
            if (blank($act['act'] ?? null) || blank($act['published_on'] ?? null) || blank($act['starts_on'] ?? null)
                || $act['published_on'] > $date || $act['starts_on'] > $date || (filled($act['ends_on'] ?? null) && $act['ends_on'] < $date)) {
                return __('Confira o ato, a publicação e a vigência em Atos autorizativos no cadastro da escola. Pendência: :authorization.', [
                    'authorization' => __($labels[$key]),
                ]);
            }
        }

        return null;
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
            || blank($student->birth_state) || blank($student->school_gender) || ! $student->birth_date) {
            return __('Confira a identificação com foto, o gênero, a nacionalidade e os dados de nascimento na ficha do estudante.');
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
