<?php

namespace App\Support;

use App\Models\Person;
use App\Models\PersonContact;
use Carbon\CarbonImmutable;

class ClassStudentContacts
{
    public static function student(?Person $student, CarbonImmutable $referenceDate): array
    {
        $contacts = collect();
        foreach ($student?->contacts ?? [] as $contact) {
            $contacts->push([
                'name' => $contact->name,
                'relationship' => PersonContact::TYPE_LABELS[$contact->relationship_type] ?? 'Contato',
                'phones' => collect([$contact->phone, $contact->secondary_phone])->filter()->unique()->values()->all(),
            ]);
        }
        foreach ($student?->relationships ?? [] as $relationship) {
            if ($person = $relationship->relatedPerson) {
                $contacts->push([
                    'name' => $person->full_name,
                    'relationship' => PersonContact::TYPE_LABELS[$relationship->relationship_type] ?? 'Contato',
                    'phones' => array_values(array_filter([$person->phone])),
                ]);
            }
        }
        // Preserve all registered family/emergency contacts, including records without the legal-guardian flag.
        $contacts = $contacts->groupBy(fn ($contact) => mb_strtolower(trim($contact['name'] ?? '')).'|'.$contact['relationship'])
            ->map(function ($group) {
                $contact = $group->first();
                $contact['phones'] = $group->pluck('phones')->flatten()->unique()->values()->all();
                return $contact;
            })->values()->all();
        $birthDate = $student?->birth_date
            ? CarbonImmutable::parse($student->birth_date->format('Y-m-d'), $referenceDate->timezone)
            : null;
        $cpf = $student?->cpf;
        $digits = preg_replace('/\D/', '', $cpf ?? '');
        if (strlen($digits) === 11) {
            $cpf = substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9);
        }

        return [
            'name' => $student?->full_name ?: '-',
            'birth_date' => $birthDate?->format('d/m/Y') ?? '-',
            'age' => $birthDate && $birthDate->lessThanOrEqualTo($referenceDate) ? (int) $birthDate->diffInYears($referenceDate) : null,
            'cpf' => $cpf ?: '-',
            'mother' => $student?->mother_name ?: '-',
            'father' => $student?->father_name ?: '-',
            'contacts' => $contacts,
        ];
    }
}
