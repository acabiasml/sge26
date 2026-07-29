<?php

namespace App\Support;

use App\Models\Person;
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
}
