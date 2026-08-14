<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Support\Str;

class InstitutionalEmailGenerator
{
    /** @var list<string> */
    private const NAME_PARTICLES = ['da', 'das', 'de', 'do', 'dos', 'e'];

    public function generate(string $fullName, ?int $exceptPersonId = null): string
    {
        $baseEmail = $this->baseEmail($fullName);
        [$localPart, $domain] = explode('@', $baseEmail, 2);
        $candidate = $baseEmail;
        $suffix = 2;

        while ($this->alreadyExists($candidate, $exceptPersonId)) {
            $candidate = $localPart.$suffix.'@'.$domain;
            $suffix++;
        }

        return $candidate;
    }

    public function baseEmail(string $fullName): string
    {
        $normalized = Str::of($fullName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        $parts = array_values(array_filter(explode(' ', $normalized)));
        $firstName = $parts[0] ?? 'pessoa';
        $secondName = collect(array_slice($parts, 1))
            ->first(fn (string $part): bool => ! in_array($part, self::NAME_PARTICLES, true));

        $localPart = Str::limit($secondName ? $firstName.'.'.$secondName : $firstName, 80, '');
        $domain = Str::lower((string) config('services.google.allowed_domain', 'ctjj.org'));

        return $localPart.'@'.$domain;
    }

    private function alreadyExists(string $email, ?int $exceptPersonId): bool
    {
        return Person::query()
            ->when($exceptPersonId, fn ($query) => $query->whereKeyNot($exceptPersonId))
            ->whereRaw('LOWER(institutional_email) = ?', [Str::lower($email)])
            ->exists();
    }
}
