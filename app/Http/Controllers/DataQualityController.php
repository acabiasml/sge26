<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonSchoolRole;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DataQualityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $schools = $this->availableSchools($request);
        $selectedSchoolId = $this->selectedSchoolId($request, $schools);
        $schoolIds = $this->schoolIdsForChecks($request, $schools, $selectedSchoolId);

        return view('data-quality.index', [
            'personChecks' => $this->personChecks($schoolIds),
            'roleChecks' => $this->roleChecks($schoolIds),
            'contactChecks' => $this->contactChecks($schoolIds),
            'schoolChecks' => $this->schoolChecks($schoolIds),
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
        ]);
    }

    /**
     * @return Collection<int, School>
     */
    private function availableSchools(Request $request): Collection
    {
        return School::query()
            ->when(! $request->user()->isAdministrator(), fn (Builder $query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Collection<int, School> $schools
     */
    private function selectedSchoolId(Request $request, Collection $schools): ?int
    {
        $schoolId = $request->integer('school_id');

        if ($schoolId < 1) {
            return null;
        }

        return $schools->contains('id', $schoolId) ? $schoolId : null;
    }

    /**
     * @param Collection<int, School> $schools
     * @return list<int>|null
     */
    private function schoolIdsForChecks(Request $request, Collection $schools, ?int $selectedSchoolId): ?array
    {
        if ($selectedSchoolId !== null) {
            return [$selectedSchoolId];
        }

        if ($request->user()->isAdministrator()) {
            return null;
        }

        return $schools->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function personChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->check(
                'Pessoas sem CPF',
                'Bloqueiam acesso completo e exigem conferência antes de emissão de documentos.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('cpf')->orWhere('cpf', '')),
                'danger'
            ),
            $this->check(
                'Pessoas sem e-mail institucional',
                'Não conseguem acessar pelo Google Workspace até receberem um e-mail @ctjj.org.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('institutional_email')->orWhere('institutional_email', '')),
                'warning'
            ),
            $this->check(
                'Pessoas com cadastro incompleto',
                'Falta CPF, data de nascimento, nome da mãe, telefone ou conclusão de cadastro.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where('active', true)
                    ->where(function (Builder $query): void {
                        $query->whereNull('cpf')
                            ->orWhere('cpf', '')
                            ->orWhereNull('birth_date')
                            ->orWhereNull('mother_name')
                            ->orWhere('mother_name', '')
                            ->orWhereNull('phone')
                            ->orWhere('phone', '')
                            ->orWhereNull('profile_completed_at');
                    }),
                'warning'
            ),
            $this->check(
                'CPFs com formato suspeito',
                'Cadastros com CPF que não possui 11 dígitos numéricos após remover máscara.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereNotNull('cpf')
                    ->where('cpf', '!=', '')
                    ->whereRaw("length(replace(replace(replace(cpf, '.', ''), '-', ''), ' ', '')) != 11"),
                'warning'
            ),
            $this->check(
                'E-mails institucionais fora do domínio',
                'O login institucional deve usar o domínio ctjj.org.',
                $this->personScope(Person::query(), $schoolIds)
                    ->whereNotNull('institutional_email')
                    ->where('institutional_email', '!=', '')
                    ->where('institutional_email', 'not like', '%@ctjj.org'),
                'danger'
            ),
            $this->check(
                'Pessoas ativas sem vínculo ativo',
                'Pessoas ativas que não aparecem como estudante, docência, gestão, equipe ou administração ativa.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where('active', true)
                    ->whereDoesntHave('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles, $schoolIds)),
                'info'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function roleChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->roleCheck(
                'Vínculos sem data de início',
                'Todo vínculo escolar, exceto administração global, deve ter data de início definida.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', '!=', PersonSchoolRole::ROLE_ADMINISTRATOR)
                    ->whereNull('started_at'),
                'warning'
            ),
            $this->roleCheck(
                'Estudantes ativos sem escola',
                'Estudantes ativos precisam estar ligados a uma escola.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_STUDENT)
                    ->where('active', true)
                    ->whereNull('school_id'),
                'danger'
            ),
            $this->roleCheck(
                'Docência ativa sem escola',
                'Vínculos de docência ativos precisam estar ligados a uma escola.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_TEACHER)
                    ->where('active', true)
                    ->whereNull('school_id'),
                'danger'
            ),
            $this->roleCheck(
                'Gestão ativa sem função',
                'Vínculos de gestão precisam indicar Direção, Coordenação ou Secretaria.',
                $this->roleScope(PersonSchoolRole::query(), $schoolIds)
                    ->where('role', PersonSchoolRole::ROLE_MANAGER)
                    ->where('active', true)
                    ->whereNull('position'),
                'warning'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function contactChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->check(
                'Estudantes menores sem responsável',
                'Estudantes menores de 18 anos precisam ter ao menos um responsável legal cadastrado.',
                $this->personScope(Person::query(), $schoolIds)
                    ->where('active', true)
                    ->whereNotNull('birth_date')
                    ->whereDate('birth_date', '>', now()->subYears(18)->toDateString())
                    ->whereHas('schoolRoles', fn (Builder $roles) => $this->activeRoleScope($roles, $schoolIds)->where('role', PersonSchoolRole::ROLE_STUDENT))
                    ->whereDoesntHave('contacts', fn (Builder $contacts) => $contacts->where('legal_guardian', true)),
                'danger'
            ),
            $this->contactCheck(
                'Responsáveis sem contato',
                'Contatos de responsáveis sem telefone e sem e-mail precisam ser complementados.',
                $this->contactScope(PersonContact::query(), $schoolIds)
                    ->where(function (Builder $query): void {
                        $query->where(fn (Builder $query) => $query->whereNull('phone')->orWhere('phone', ''))
                            ->where(fn (Builder $query) => $query->whereNull('secondary_phone')->orWhere('secondary_phone', ''))
                            ->where(fn (Builder $query) => $query->whereNull('email')->orWhere('email', ''));
                    }),
                'warning'
            ),
        ]);
    }

    /**
     * @param list<int>|null $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    private function schoolChecks(?array $schoolIds): Collection
    {
        return collect([
            $this->schoolCheck(
                'Escolas sem CNPJ',
                'O CNPJ será necessário em relatórios e documentos oficiais.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('cnpj')->orWhere('cnpj', '')),
                'warning'
            ),
            $this->schoolCheck(
                'Escolas sem INEP',
                'O código INEP ajuda a identificar corretamente a unidade escolar.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(fn (Builder $query) => $query->whereNull('inep')->orWhere('inep', '')),
                'warning'
            ),
            $this->schoolCheck(
                'Escolas sem endereço completo',
                'Endereço, cidade e UF devem estar preenchidos para documentos emitidos pelo sistema.',
                $this->schoolScope(School::query(), $schoolIds)
                    ->where(function (Builder $query): void {
                        $query->whereNull('address')
                            ->orWhere('address', '')
                            ->orWhereNull('city')
                            ->orWhere('city', '')
                            ->orWhereNull('state')
                            ->orWhere('state', '');
                    }),
                'info'
            ),
        ]);
    }

    /**
     * @param Builder<Person> $query
     * @return array<string, mixed>
     */
    private function check(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('full_name')
                ->limit(8)
                ->get(['id', 'full_name', 'institutional_email', 'cpf']),
            'type' => 'people',
        ];
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @return array<string, mixed>
     */
    private function roleCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with(['person', 'school'])
                ->orderBy('role')
                ->limit(8)
                ->get(),
            'type' => 'roles',
        ];
    }

    /**
     * @param Builder<PersonContact> $query
     * @return array<string, mixed>
     */
    private function contactCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->with('person')
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'type' => 'contacts',
        ];
    }

    /**
     * @param Builder<School> $query
     * @return array<string, mixed>
     */
    private function schoolCheck(string $title, string $description, Builder $query, string $severity): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => (clone $query)->count(),
            'items' => (clone $query)
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'city', 'state']),
            'type' => 'schools',
        ];
    }

    /**
     * @param Builder<Person> $query
     * @param list<int>|null $schoolIds
     * @return Builder<Person>
     */
    private function personScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->where('active', true)
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds));
            });
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonSchoolRole>
     */
    private function roleScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->whereHas('person', fn (Builder $person) => $person->where('active', true))
            ->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('school_id', $schoolIds));
    }

    /**
     * @param Builder<PersonContact> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonContact>
     */
    private function contactScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query
            ->whereHas('person', fn (Builder $person) => $person->where('active', true))
            ->when($schoolIds !== null, function (Builder $query) use ($schoolIds): void {
                $query->whereHas('person.schoolRoles', fn (Builder $roles) => $roles->whereIn('school_id', $schoolIds));
            });
    }

    /**
     * @param Builder<School> $query
     * @param list<int>|null $schoolIds
     * @return Builder<School>
     */
    private function schoolScope(Builder $query, ?array $schoolIds): Builder
    {
        return $query->when($schoolIds !== null, fn (Builder $query) => $query->whereIn('id', $schoolIds));
    }

    /**
     * @param Builder<PersonSchoolRole> $query
     * @param list<int>|null $schoolIds
     * @return Builder<PersonSchoolRole>
     */
    private function activeRoleScope(Builder $query, ?array $schoolIds = null): Builder
    {
        return $this->roleScope($query, $schoolIds)
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }
}
