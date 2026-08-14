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

        return 'Documento bloqueado: complete o cadastro oficial da escola antes de emitir. Campos pendentes: '
            .implode(', ', $missing)
            .'. Abra o cadastro da escola e revise os dados do papel timbrado.';
    }

    public static function personMessage(Person $person): ?string
    {
        $missing = $person->missingSchoolDocumentFields();

        if ($missing === []) {
            return null;
        }

        return 'Documento bloqueado: complete o cadastro da pessoa antes de emitir. Campos pendentes: '
            .implode(', ', $missing)
            .'. Abra a ficha da pessoa e revise os dados civis e de endereço.';
    }

    public static function studentMessage(Person $student, bool $missingCpfConfirmed = false): ?string
    {
        $missing = array_values(array_filter(
            $student->missingSchoolDocumentFields(),
            fn (string $field): bool => $field !== 'CPF'
        ));

        if ($missing !== []) {
            return 'Documento bloqueado: complete o cadastro do estudante antes de emitir. Campos pendentes: '
                .implode(', ', $missing).'.';
        }

        if (! self::hasParentCpf($student)) {
            return 'Documento bloqueado: cadastre o CPF da mãe ou do pai na seção Responsáveis e contatos.';
        }

        if (self::studentHasNoCpf($student) && ! $missingCpfConfirmed) {
            return 'Confirme que o documento será emitido sem o CPF do estudante.';
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
