<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_store_student_social_program_data(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin.social@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.store'), $this->personPayload([
                'full_name' => 'Estudante com NIS',
                'institutional_email' => 'estudante.nis@ctjj.org',
                'cpf' => '98765432100',
                'nis' => '12345678901',
                'receives_federal_aid' => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('people', [
            'full_name' => 'Estudante Com Nis',
            'nis' => '12345678901',
            'receives_federal_aid' => true,
        ]);
    }

    public function test_only_active_administrator_cannot_change_their_own_institutional_email(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $this->actingAs($admin)
            ->put(route('people.update', $admin->person), $this->personPayload([
                'institutional_email' => 'novo.admin@ctjj.org',
            ]))
            ->assertRedirect(route('people.show', $admin->person));

        $this->assertSame('admin@ctjj.org', $admin->person->refresh()->institutional_email);
    }

    public function test_administrator_can_change_their_own_identity_data_and_email_when_another_administrator_exists(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'outro.admin@ctjj.org');

        $this->actingAs($admin)
            ->put(route('people.update', $admin->person), $this->personPayload([
                'full_name' => 'Outro Nome',
                'institutional_email' => 'novo.admin@ctjj.org',
                'cpf' => '99988877766',
                'birth_date' => '2000-05-10',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
                'mother_name' => 'Outra Mãe',
            ]))
            ->assertRedirect(route('people.show', $admin->person));

        $admin->person->refresh();

        $this->assertSame('Outro Nome', $admin->person->full_name);
        $this->assertSame('novo.admin@ctjj.org', $admin->person->institutional_email);
        $this->assertSame('99988877766', $admin->person->cpf);
        $this->assertSame('2000-05-10', $admin->person->birth_date->toDateString());
        $this->assertSame('Outra Mãe', $admin->person->mother_name);
    }

    public function test_manager_cannot_change_their_own_sensitive_identity_data(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('people.update', $manager->person), $this->personPayload([
                'full_name' => 'Outro Nome',
                'institutional_email' => 'outra.gestao@ctjj.org',
                'cpf' => '99988877766',
                'birth_date' => '2000-05-10',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
                'mother_name' => 'Outra Mãe',
            ]))
            ->assertRedirect(route('people.show', $manager->person));

        $manager->person->refresh();

        $this->assertSame('Pessoa Gestao@ctjj.org', $manager->person->full_name);
        $this->assertSame('gestao@ctjj.org', $manager->person->institutional_email);
        $this->assertNotSame('99988877766', $manager->person->cpf);
        $this->assertSame('1990-01-01', $manager->person->birth_date->toDateString());
        $this->assertSame('Maria da Silva', $manager->person->mother_name);
    }

    public function test_administrator_can_change_another_persons_institutional_email(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $person = $this->person(email: 'professor@ctjj.org');

        $this->actingAs($admin)
            ->put(route('people.update', $person), $this->personPayload([
                'institutional_email' => 'professor.novo@ctjj.org',
            ]))
            ->assertRedirect(route('people.show', $person));

        $this->assertSame('professor.novo@ctjj.org', $person->refresh()->institutional_email);
    }

    public function test_cpf_and_institutional_email_must_be_unique(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $existing = $this->person(email: 'existente@ctjj.org', cpf: '11122233344');

        $this->actingAs($admin)
            ->post(route('people.store'), $this->personPayload([
                'full_name' => 'Pessoa Duplicada',
                'institutional_email' => $existing->institutional_email,
                'cpf' => $existing->cpf,
            ]))
            ->assertSessionHasErrors(['institutional_email', 'cpf']);
    }

    public function test_mother_name_is_required_when_registering_person(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);

        $this->actingAs($admin)
            ->post(route('people.store'), $this->personPayload([
                'mother_name' => null,
                'initial_role' => PersonSchoolRole::ROLE_STUDENT,
                'initial_school_id' => $school->id,
                'initial_started_at' => now()->toDateString(),
            ]))
            ->assertSessionHasErrors(['mother_name']);
    }

    public function test_administrator_can_update_active_person_without_completing_registration_data(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = Person::query()->create([
            'full_name' => 'Cadastro Incompleto',
            'active' => false,
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->put(route('people.update', $person), $this->personPayload([
                'full_name' => 'Cadastro Revisado',
                'institutional_email' => null,
                'cpf' => null,
                'birth_date' => null,
                'birth_city' => null,
                'birth_state' => null,
                'nationality' => null,
                'mother_name' => null,
                'address' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
            ]))
            ->assertRedirect(route('people.show', $person))
            ->assertSessionDoesntHaveErrors();

        $person->refresh();

        $this->assertTrue($person->active);
        $this->assertSame('Cadastro Revisado', $person->full_name);
        $this->assertNull($person->cpf);
        $this->assertNull($person->mother_name);
    }

    public function test_manager_must_complete_required_data_when_updating_active_person(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestao@ctjj.org');
        $person = Person::query()->create([
            'full_name' => 'Cadastro Incompleto',
            'active' => true,
        ]);

        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($manager)
            ->put(route('people.update', $person), $this->personPayload([
                'cpf' => null,
                'birth_date' => null,
                'nationality' => null,
                'mother_name' => null,
                'address' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'active' => '1',
            ]))
            ->assertSessionHasErrors([
                'cpf',
                'birth_date',
                'nationality',
                'mother_name',
                'address',
                'city',
                'state',
                'postal_code',
            ]);
    }

    public function test_person_form_shows_nationality_select_with_brazil_first(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $response = $this->actingAs($admin)->get(route('people.create'));

        $response->assertOk()
            ->assertSee('<select id="nationality"', false)
            ->assertSeeInOrder(['Brasil - Brasileira', 'Argentina - Argentina', 'Portugal - Portuguesa']);
    }

    public function test_legacy_brazil_nationality_is_not_duplicated_in_select(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $person = $this->person(email: 'brasil@ctjj.org');
        $person->update(['nationality' => 'Brasil']);

        $response = $this->actingAs($admin)->get(route('people.edit', $person));

        $response->assertOk()
            ->assertSee('<option value="Brasileira" selected>Brasil - Brasileira</option>', false)
            ->assertDontSee('<option value="Brasil" selected>Brasil</option>', false);
    }

    public function test_foreign_nationality_does_not_require_birth_city_or_state(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.store'), $this->personPayload([
                'full_name' => 'Pessoa Estrangeira',
                'institutional_email' => 'estrangeira@ctjj.org',
                'cpf' => '55544433322',
                'nationality' => 'Portuguesa',
                'birth_city' => null,
                'birth_state' => null,
            ]))
            ->assertRedirect();

        $person = Person::query()->where('institutional_email', 'estrangeira@ctjj.org')->firstOrFail();

        $this->assertNull($person->birth_city);
        $this->assertNull($person->birth_state);
        $this->assertSame('Portuguesa', $person->nationality);
    }

    public function test_brazilian_nationality_requires_birth_city_and_state(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);

        $this->actingAs($admin)
            ->post(route('people.store'), $this->personPayload([
                'nationality' => 'Brasileira',
                'birth_city' => null,
                'birth_state' => null,
                'initial_role' => PersonSchoolRole::ROLE_STUDENT,
                'initial_school_id' => $school->id,
                'initial_started_at' => now()->toDateString(),
            ]))
            ->assertSessionHasErrors(['birth_city', 'birth_state']);
    }

    public function test_non_administrator_role_requires_start_date(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'gestor@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.roles.store', $person), [
                'role' => PersonSchoolRole::ROLE_MANAGER,
                'school_id' => $school->id,
                'position' => PersonSchoolRole::POSITION_DIRECTOR,
                'active' => '1',
            ])
            ->assertSessionHasErrors([
                'started_at' => 'O campo início é obrigatório.',
            ]);
    }

    public function test_manager_role_requires_position(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'gestor@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.roles.store', $person), [
                'role' => PersonSchoolRole::ROLE_MANAGER,
                'school_id' => $school->id,
                'started_at' => now()->toDateString(),
                'active' => '1',
            ])
            ->assertSessionHasErrors(['position']);
    }

    public function test_manager_role_accepts_official_positions(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'diretor@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.roles.store', $person), [
                'role' => PersonSchoolRole::ROLE_MANAGER,
                'school_id' => $school->id,
                'position' => PersonSchoolRole::POSITION_DIRECTOR,
                'started_at' => now()->toDateString(),
                'active' => '1',
            ])
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('person_school_roles', [
            'person_id' => $person->id,
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'position' => PersonSchoolRole::POSITION_DIRECTOR,
        ]);

        $this->assertSame('Gestão - Direção', $person->schoolRoles()->firstOrFail()->label());
    }

    public function test_administrator_role_uses_current_date_as_start_date(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $person = $this->person(email: 'novo.admin@ctjj.org');

        $this->actingAs($admin)
            ->post(route('people.roles.store', $person), [
                'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
                'school_id' => null,
                'started_at' => now()->subYear()->toDateString(),
                'active' => '1',
            ])
            ->assertRedirect(route('people.show', $person));

        $role = $person->schoolRoles()
            ->where('role', PersonSchoolRole::ROLE_ADMINISTRATOR)
            ->firstOrFail();

        $this->assertSame(now()->toDateString(), $role->started_at->toDateString());
    }

    public function test_person_active_status_follows_role_deactivation(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'professor@ctjj.org');
        $role = $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('people.roles.deactivate', [$person, $role]))
            ->assertRedirect(route('people.show', $person));

        $role->refresh();

        $this->assertFalse($role->active);
        $this->assertSame(now()->toDateString(), $role->ended_at->toDateString());
        $this->assertFalse($person->refresh()->active);
    }

    public function test_person_form_does_not_show_manual_active_control(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $this->actingAs($admin)
            ->get(route('people.edit', $admin->person))
            ->assertOk()
            ->assertDontSee('Cadastro ativo');
    }

    public function test_only_active_administrator_cannot_deactivate_or_remove_their_administration_role(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $role = $admin->person->schoolRoles()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('people.roles.deactivate', [$admin->person, $role]))
            ->assertRedirect()
            ->assertSessionHasErrors(['role']);

        $this->actingAs($admin)
            ->delete(route('people.roles.destroy', [$admin->person, $role]))
            ->assertRedirect()
            ->assertSessionHasErrors(['role']);

        $this->assertTrue($role->refresh()->active);
        $this->assertDatabaseHas('person_school_roles', ['id' => $role->id]);
    }

    public function test_administration_role_can_be_deactivated_when_another_active_administration_exists(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'outra.admin@ctjj.org');
        $role = $admin->person->schoolRoles()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('people.roles.deactivate', [$admin->person, $role]))
            ->assertRedirect(route('people.show', $admin->person));

        $this->assertFalse($role->refresh()->active);
        $this->assertFalse($admin->person->refresh()->active);
    }

    public function test_role_can_be_deactivated_without_removing_history(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'docencia@ctjj.org');
        $role = $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('people.roles.deactivate', [$person, $role]))
            ->assertRedirect(route('people.show', $person));

        $role->refresh();

        $this->assertFalse($role->active);
        $this->assertSame(now()->toDateString(), $role->ended_at->toDateString());
        $this->assertFalse($person->refresh()->active);
        $this->assertDatabaseHas('person_school_roles', ['id' => $role->id]);
    }

    public function test_role_can_be_activated_again(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');
        $school = School::query()->create(['name' => 'Escola A']);
        $person = $this->person(email: 'estudante@ctjj.org');
        $role = $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => false,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('people.roles.activate', [$person, $role]))
            ->assertRedirect(route('people.show', $person));

        $role->refresh();

        $this->assertTrue($role->active);
        $this->assertNull($role->ended_at);
        $this->assertTrue($person->refresh()->active);
    }

    public function test_highest_active_role_is_shown_first(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $user = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id, email: 'multi@ctjj.org');

        $user->person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => '2026-01-01',
        ]);

        $this->assertStringStartsWith('Docência / Escola A', $user->refresh()->activeRoleLabel());
    }

    private function userWithRole(string $role, ?int $schoolId = null, string $email = 'usuario@ctjj.org'): User
    {
        $person = $this->person(email: $email);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function personPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Pessoa Teste',
            'social_name' => null,
            'institutional_email' => 'pessoa@ctjj.org',
            'personal_email' => 'pessoa@gmail.com',
            'cpf' => '12345678901',
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria da Silva',
            'father_name' => 'José da Silva',
            'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'active' => '1',
        ], $overrides);
    }

    private function person(string $email, ?string $cpf = null): Person
    {
        return Person::query()->create([
            'full_name' => 'Pessoa '.$email,
            'institutional_email' => $email,
            'cpf' => $cpf ?? fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria da Silva',
            'father_name' => 'José da Silva',
            'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'active' => false,
            'profile_completed_at' => now(),
        ]);
    }
}
