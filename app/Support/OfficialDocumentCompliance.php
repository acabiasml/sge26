<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\School;
use Illuminate\Support\Str;

class OfficialDocumentCompliance
{
    public static function authorizationMessage(School $school, array $stages): ?string
    {
        if ($school->state !== 'MT') {
            return null;
        }
        $date = now('America/Cuiaba')->toDateString();
        $keys = array_unique(['accreditation', ...array_intersect($stages, [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL])]);
        foreach ($keys as $key) {
            $act = $school->document_authorizations[$key] ?? [];
            if (blank($act['act'] ?? null) || blank($act['published_on'] ?? null) || blank($act['starts_on'] ?? null)
                || $act['published_on'] > $date || $act['starts_on'] > $date || (filled($act['ends_on'] ?? null) && $act['ends_on'] < $date)) {
                return __('Confira o ato, a publicação e a vigência em Atos autorizativos no cadastro da escola. Pendência: :authorization.', [
                    'authorization' => __(School::DOCUMENT_AUTHORIZATIONS[$key]),
                ]);
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
