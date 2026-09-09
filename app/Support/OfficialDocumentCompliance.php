<?php

namespace App\Support;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\School;

class OfficialDocumentCompliance
{
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
