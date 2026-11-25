<?php

namespace App\Support;

use App\Models\StudentAcademicHistory;

final class HistoryDocumentVersion
{
    public static function of(StudentAcademicHistory $history): string
    {
        $history->loadMissing('student', 'school', 'years.records', 'components.records.year');

        $school = $history->school;

        return app(CredentialRegistry::class)->digest(self::snapshot($history) + [
            'student_identity' => $history->student->only(['full_name', 'social_name', 'cpf', 'birth_date', 'birth_city', 'birth_state', 'nationality', 'mother_name', 'father_name', 'school_cin_confirmed', 'school_identity_document', 'school_voter_registration', 'school_voter_exemption', 'school_gender']),
            'letterhead' => $school ? $school->only(['name', 'cnpj', 'address', 'number', 'district', 'city', 'state', 'postal_code'])
                + ['institutional_line' => $school->letterheadTextsForStages([$history->education_stage]), 'authorizations' => $school->document_authorizations] : null,
        ]);
    }

    private static function snapshot(StudentAcademicHistory $history): array
    {
        $clean = function ($value) use (&$clean) {
            if (! is_array($value)) {
                return $value;
            }
            unset($value['created_at'], $value['updated_at']);

            return array_map($clean, $value);
        };

        return $clean([
            'history' => $history->attributesToArray(),
            'years' => $history->years->sortBy('id')->map(fn ($year) => $year->attributesToArray())->values()->all(),
            'components' => $history->components->sortBy('id')->map(fn ($component) => $component->attributesToArray() + [
                'records' => $component->records->sortBy('id')->map->attributesToArray()->values()->all(),
            ])->values()->all(),
        ]);
    }
}
