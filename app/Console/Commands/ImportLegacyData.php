<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import {--fresh : Remove dados importados das bases legadas antes de importar}';

    protected $description = 'Importa escolas, pessoas, vínculos e responsáveis das três bases legadas.';

    /**
     * @var array<string, string>
     */
    private array $sources = [
        'beaba' => 'database/legacy/u810745753_beaba.sql',
        'lar' => 'database/legacy/u810745753_lar.sql',
        'laura' => 'database/legacy/u810745753_laura.sql',
    ];

    /**
     * @var array<string, array<int, int>>
     */
    private array $personIdMap = [];

    /**
     * @var array<string, int>
     */
    private array $schoolIdMap = [];

    /**
     * @var array<string, array<int, true>>
     */
    private array $activeLegacyUserIds = [];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->removePreviousImport();
        }

        Model::withoutEvents(function (): void {
            foreach ($this->sources as $source => $path) {
                $this->importSource($source, base_path($path));
            }
        });

        $this->info('Importação legada concluída.');

        return self::SUCCESS;
    }

    private function removePreviousImport(): void
    {
        DB::transaction(function (): void {
            $peopleIds = Person::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->whereDoesntHave('user')
                ->pluck('id');

            PersonRelationship::query()
                ->whereIn('person_id', $peopleIds)
                ->orWhereIn('related_person_id', $peopleIds)
                ->delete();

            PersonContact::query()
                ->whereIn('person_id', $peopleIds)
                ->orWhereIn('legacy_source', array_keys($this->sources))
                ->delete();

            PersonSchoolRole::query()
                ->whereIn('person_id', $peopleIds)
                ->delete();

            Person::query()
                ->whereIn('id', $peopleIds)
                ->delete();

            School::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();
        });
    }

    private function importSource(string $source, string $path): void
    {
        if (! is_file($path)) {
            $this->warn("Arquivo não encontrado: {$path}");

            return;
        }

        DB::transaction(function () use ($source, $path): void {
            $tables = $this->parseDump(file_get_contents($path) ?: '');
            $school = $this->importSchool($source, $tables['escolas'][0] ?? []);

            $this->schoolIdMap[$source] = $school->id;
            $this->personIdMap[$source] = [];
            $this->activeLegacyUserIds[$source] = $this->activeLegacyUserIds($tables);

            foreach ($tables['users'] ?? [] as $legacyUser) {
                $person = $this->importPerson($source, $legacyUser);
                $this->personIdMap[$source][(int) $legacyUser['id']] = $person->id;
                $this->importRoleFromType($person, $school, $legacyUser);
            }

            $this->importSchoolManagementRoles($source, $school, $tables['escolas'][0] ?? []);
            $this->importTeacherRolesFor2026($source, $school, $tables);
            $this->importContacts($source, $tables['users'] ?? []);

            $this->line("{$source}: ".count($tables['users'] ?? []).' pessoas importadas/atualizadas.');
        });
    }

    /**
     * @param array<string, mixed> $legacySchool
     */
    private function importSchool(string $source, array $legacySchool): School
    {
        $school = School::query()->firstOrNew([
            'legacy_source' => $source,
            'legacy_id' => (int) ($legacySchool['id'] ?? 0),
        ]);

        $school->fill([
            'name' => $legacySchool['nome'] ?? ucfirst($source),
            'legal_name' => $legacySchool['razao'] ?? null,
            'cnpj' => $this->onlyDigits($legacySchool['cnpj'] ?? null),
            'founded_at' => $this->dateOrNull($legacySchool['fundacao'] ?? null),
            'phone' => $legacySchool['telefone'] ?? null,
            'email' => $this->validEmail($legacySchool['email'] ?? null),
            'website' => $this->urlOrNull($legacySchool['site'] ?? null),
            'letterhead_text' => $legacySchool['info'] ?? null,
            'address' => $legacySchool['endereco'] ?? null,
            'district' => $legacySchool['bairro'] ?? null,
            'number' => $legacySchool['numero'] ?? null,
            'city' => $legacySchool['cidade'] ?? null,
            'state' => $this->stateOrNull($legacySchool['estado'] ?? null),
            'postal_code' => $legacySchool['cep'] ?? null,
            'active' => true,
            'legacy_metadata' => [
                'diretor' => $legacySchool['diretor'] ?? null,
                'coordenador' => $legacySchool['coordenador'] ?? null,
                'secretario' => $legacySchool['secretario'] ?? null,
            ],
        ]);
        $school->save();

        return $school;
    }

    /**
     * @param array<string, mixed> $legacyUser
     */
    private function importPerson(string $source, array $legacyUser): Person
    {
        $cpf = $this->validCpf($legacyUser['cpf'] ?? null);
        $legacyId = (int) ($legacyUser['id'] ?? 0);

        $person = Person::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', $legacyId)
            ->first();

        if (! $person && $cpf) {
            $person = Person::query()->where('cpf', $cpf)->first();
        }

        if (! $person && $this->isAcabias($legacyUser)) {
            $person = Person::query()
                ->where('full_name', 'like', '%Acabias%')
                ->first();
        }

        $person ??= new Person();

        $email = $this->validEmail($legacyUser['email'] ?? null);
        $institutionalEmail = $email && str_ends_with(strtolower($email), '@ctjj.org') ? $email : null;
        $personalEmail = $email && ! $institutionalEmail ? $email : ($person->personal_email ?? null);

        $person->fill([
            'legacy_id' => $legacyId,
            'legacy_source' => $source,
            'legacy_code' => $legacyUser['codigo'] ?? null,
            'student_inep' => ($legacyUser['tipo'] ?? null) === 'estud' ? ($legacyUser['inep'] ?? null) : null,
            'full_name' => $legacyUser['nome'] ?? 'Pessoa legada '.$source.' #'.$legacyId,
            'social_name' => $legacyUser['nomesocial'] ?? null,
            'cpf' => $cpf,
            'birth_date' => $this->dateOrNull($legacyUser['nascimento'] ?? null),
            'mother_name' => $legacyUser['genitora'] ?? $person->mother_name,
            'father_name' => $legacyUser['genitor'] ?? $person->father_name,
            'institutional_email' => $institutionalEmail ?? $person->institutional_email,
            'personal_email' => $personalEmail,
            'phone' => $legacyUser['telefone1'] ?? $legacyUser['telefone2'] ?? null,
            'address' => $legacyUser['endereco'] ?? null,
            'number' => $legacyUser['endnumero'] ?? null,
            'district' => $legacyUser['endbairro'] ?? null,
            'city' => $legacyUser['endcidade'] ?? null,
            'state' => $this->stateOrNull($legacyUser['enduf'] ?? null),
            'postal_code' => $legacyUser['endcep'] ?? null,
            'address_complement' => $legacyUser['endcomplemento'] ?? null,
            'active' => $this->shouldActivateLegacyUser($source, $legacyUser),
            'profile_completed_at' => null,
            'legacy_metadata' => [
                'tipo' => $legacyUser['tipo'] ?? null,
                'arquivado' => $legacyUser['arquivado'] ?? null,
                'sexo' => $legacyUser['sexo'] ?? null,
                'cor' => $legacyUser['cor'] ?? null,
                'gemeo' => $legacyUser['gemeo'] ?? null,
                'nacionalidade' => $legacyUser['nacionalidade'] ?? null,
                'naturalidade' => $legacyUser['naturalidade'] ?? null,
                'naturalidade_uf' => $legacyUser['naturaif'] ?? null,
                'identidade' => $legacyUser['identidade'] ?? null,
                'identidade_emissor' => $legacyUser['identemissor'] ?? null,
                'identidade_uf' => $legacyUser['identuf'] ?? null,
                'certidao' => $legacyUser['certidao'] ?? null,
                'cartao_sus' => $legacyUser['cartaosus'] ?? null,
                'nis' => $legacyUser['nis'] ?? null,
                'genitora' => $legacyUser['genitora'] ?? null,
                'genitor' => $legacyUser['genitor'] ?? null,
                'responsavel' => $legacyUser['responsavel'] ?? null,
                'responsavel_cpf' => $legacyUser['responcpf'] ?? null,
                'responsavel_telefone_1' => $legacyUser['respontel1'] ?? null,
                'responsavel_telefone_2' => $legacyUser['respontel2'] ?? null,
            ],
        ]);
        $person->save();

        return $person;
    }

    /**
     * @param array<string, mixed> $legacyUser
     */
    private function importRoleFromType(Person $person, School $school, array $legacyUser): void
    {
        $role = match ($legacyUser['tipo'] ?? null) {
            'admin' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'prof' => PersonSchoolRole::ROLE_TEACHER,
            'estud' => PersonSchoolRole::ROLE_STUDENT,
            'apoio' => PersonSchoolRole::ROLE_EMPLOYEE,
            default => null,
        };

        if (! $role) {
            return;
        }

        $schoolId = $role === PersonSchoolRole::ROLE_ADMINISTRATOR ? null : $school->id;

        PersonSchoolRole::query()->updateOrCreate(
            [
                'person_id' => $person->id,
                'school_id' => $schoolId,
                'role' => $role,
                'position' => null,
            ],
                [
                'active' => $role === PersonSchoolRole::ROLE_ADMINISTRATOR
                    ? $this->isAcabias($legacyUser)
                    : $person->active,
                'started_at' => null,
                'ended_at' => (
                    $role === PersonSchoolRole::ROLE_ADMINISTRATOR
                        ? $this->isAcabias($legacyUser)
                        : $person->active
                ) ? null : now()->toDateString(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $legacySchool
     */
    private function importSchoolManagementRoles(string $source, School $school, array $legacySchool): void
    {
        $positions = [
            'diretor' => PersonSchoolRole::POSITION_DIRECTOR,
            'coordenador' => PersonSchoolRole::POSITION_COORDINATOR,
            'secretario' => PersonSchoolRole::POSITION_SECRETARY,
        ];

        foreach ($positions as $legacyField => $position) {
            $legacyPersonId = (int) ($legacySchool[$legacyField] ?? 0);
            $personId = $this->personIdMap[$source][$legacyPersonId] ?? null;

            if (! $personId) {
                continue;
            }

            $person = Person::query()->find($personId);

            if ($person) {
                $person->forceFill(['active' => true])->save();
            }

            PersonSchoolRole::query()->updateOrCreate(
                [
                    'person_id' => $personId,
                    'school_id' => $school->id,
                    'role' => PersonSchoolRole::ROLE_MANAGER,
                    'position' => $position,
                ],
                [
                    'active' => true,
                    'started_at' => null,
                    'ended_at' => null,
                ]
            );
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     */
    private function importTeacherRolesFor2026(string $source, School $school, array $tables): void
    {
        foreach ($this->teacherLegacyUserIdsFor2026($tables) as $legacyPersonId) {
            $personId = $this->personIdMap[$source][$legacyPersonId] ?? null;

            if (! $personId) {
                continue;
            }

            Person::query()
                ->whereKey($personId)
                ->update(['active' => true]);

            PersonSchoolRole::query()->updateOrCreate(
                [
                    'person_id' => $personId,
                    'school_id' => $school->id,
                    'role' => PersonSchoolRole::ROLE_TEACHER,
                    'position' => null,
                ],
                [
                    'active' => true,
                    'started_at' => null,
                    'ended_at' => null,
                ]
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $legacyUsers
     */
    private function importContacts(string $source, array $legacyUsers): void
    {
        foreach ($legacyUsers as $legacyUser) {
            $personId = $this->personIdMap[$source][(int) $legacyUser['id']] ?? null;

            if (! $personId || ($legacyUser['tipo'] ?? null) !== 'estud') {
                continue;
            }

            $this->importTextContact($source, $personId, $legacyUser['genitora'] ?? null, PersonContact::TYPE_MOTHER);
            $this->importTextContact($source, $personId, $legacyUser['genitor'] ?? null, PersonContact::TYPE_FATHER);
            $this->importTextContact(
                $source,
                $personId,
                $legacyUser['responsavel'] ?? null,
                PersonContact::TYPE_LEGAL_GUARDIAN,
                $legacyUser['responcpf'] ?? null,
                $legacyUser['respontel1'] ?? null,
                $legacyUser['respontel2'] ?? null
            );
        }
    }

    private function importTextContact(
        string $source,
        int $personId,
        ?string $name,
        string $type,
        ?string $cpf = null,
        ?string $phone = null,
        ?string $secondaryPhone = null
    ): void {
        $name = trim((string) $name);

        if ($name === '') {
            return;
        }

        PersonContact::query()->updateOrCreate(
            [
                'person_id' => $personId,
                'relationship_type' => $type,
                'name' => $name,
            ],
            [
                'cpf' => $this->validCpf($cpf),
                'phone' => $phone,
                'secondary_phone' => $secondaryPhone,
                'legal_guardian' => $type === PersonContact::TYPE_LEGAL_GUARDIAN,
                'emergency_contact' => $type === PersonContact::TYPE_LEGAL_GUARDIAN,
                'notes' => 'Importado da base '.$source.'.',
                'legacy_source' => $source,
                'legacy_metadata' => [
                    'original_name' => $name,
                ],
            ]
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function parseDump(string $sql): array
    {
        preg_match_all('/INSERT INTO `([^`]+)` \((.*?)\) VALUES\s*(.*?);/s', $sql, $matches, PREG_SET_ORDER);

        $tables = [];

        foreach ($matches as $match) {
            $columns = array_map(fn (string $column): string => trim($column, " `\r\n\t"), explode(',', $match[2]));

            foreach ($this->splitRows(trim($match[3])) as $row) {
                $fields = str_getcsv($row, ',', "'", '\\');
                $record = [];

                foreach ($columns as $index => $column) {
                    $value = $fields[$index] ?? null;
                    $value = is_string($value) ? trim($value) : $value;
                    $record[$column] = ($value === '' || $value === 'NULL') ? null : $value;
                }

                $tables[$match[1]][] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function splitRows(string $values): array
    {
        $rows = [];
        $depth = 0;
        $quote = false;
        $escape = false;
        $buffer = '';

        for ($i = 0, $length = strlen($values); $i < $length; $i++) {
            $char = $values[$i];

            if ($quote) {
                $buffer .= $char;

                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'") {
                    $quote = false;
                }

                continue;
            }

            if ($char === "'") {
                $quote = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $buffer .= $char;
                }

                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $rows[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }

                continue;
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $rows;
    }

    private function validCpf(?string $value): ?string
    {
        $digits = $this->onlyDigits($value);

        return strlen($digits) === 11 ? $digits : null;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     * @return array<int, true>
     */
    private function activeLegacyUserIds(array $tables): array
    {
        $calendar2026Ids = collect($tables['calendarios'] ?? [])
            ->filter(fn (array $calendar): bool => (int) ($calendar['ano'] ?? 0) === 2026)
            ->map(fn (array $calendar): int => (int) $calendar['id'])
            ->all();

        $course2026Ids = collect($tables['cursos'] ?? [])
            ->filter(fn (array $course): bool => in_array((int) ($course['calendarios_id'] ?? 0), $calendar2026Ids, true))
            ->map(fn (array $course): int => (int) $course['id'])
            ->all();

        $activeIds = [];

        foreach ($tables['turmas'] ?? [] as $enrollment) {
            if (! in_array((int) ($enrollment['cursos_id'] ?? 0), $course2026Ids, true)) {
                continue;
            }

            if (($enrollment['status'] ?? null) === 'transferido') {
                continue;
            }

            $activeIds[(int) $enrollment['users_id']] = true;
        }

        foreach ($this->teacherLegacyUserIdsFor2026($tables) as $teacherId) {
            $activeIds[$teacherId] = true;
        }

        foreach (($tables['escolas'][0] ?? []) as $field => $value) {
            if (! in_array($field, ['diretor', 'coordenador', 'secretario'], true)) {
                continue;
            }

            $managerId = (int) $value;

            if ($managerId > 0) {
                $activeIds[$managerId] = true;
            }
        }

        return $activeIds;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     * @return list<int>
     */
    private function teacherLegacyUserIdsFor2026(array $tables): array
    {
        $calendar2026Ids = collect($tables['calendarios'] ?? [])
            ->filter(fn (array $calendar): bool => (int) ($calendar['ano'] ?? 0) === 2026)
            ->map(fn (array $calendar): int => (int) $calendar['id'])
            ->all();

        $course2026Ids = collect($tables['cursos'] ?? [])
            ->filter(fn (array $course): bool => in_array((int) ($course['calendarios_id'] ?? 0), $calendar2026Ids, true))
            ->map(fn (array $course): int => (int) $course['id'])
            ->all();

        return collect($tables['componentes'] ?? [])
            ->filter(fn (array $component): bool => in_array((int) ($component['cursos_id'] ?? 0), $course2026Ids, true))
            ->map(fn (array $component): int => (int) ($component['professor'] ?? 0))
            ->filter(fn (int $teacherId): bool => $teacherId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $legacyUser
     */
    private function shouldActivateLegacyUser(string $source, array $legacyUser): bool
    {
        return $this->isAcabias($legacyUser)
            || isset($this->activeLegacyUserIds[$source][(int) ($legacyUser['id'] ?? 0)]);
    }

    private function onlyDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function validEmail(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function dateOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function stateOrNull(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) ? $value : null;
    }

    private function urlOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    /**
     * @param array<string, mixed> $legacyUser
     */
    private function isAcabias(array $legacyUser): bool
    {
        return str_contains(Str::lower($legacyUser['nome'] ?? ''), 'acabias');
    }
}
