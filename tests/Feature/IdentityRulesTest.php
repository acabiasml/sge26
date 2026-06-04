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

    public function test_person_cannot_change_their_own_institutional_email(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $this->actingAs($admin)
            ->put(route('people.update', $admin->person), $this->personPayload([
                'institutional_email' => 'novo.admin@ctjj.org',
            ]))
            ->assertRedirect(route('people.show', $admin->person));

        $this->assertSame('admin@ctjj.org', $admin->person->refresh()->institutional_email);
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
            ->assertSessionHasErrors(['started_at']);
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

    public function test_deactivating_person_ends_active_roles(): void
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
            ->put(route('people.update', $person), $this->personPayload([
                'institutional_email' => $person->institutional_email,
                'active' => '0',
            ]))
            ->assertRedirect(route('people.show', $person));

        $role->refresh();

        $this->assertFalse($role->active);
        $this->assertSame(now()->toDateString(), $role->ended_at->toDateString());
    }

    public function test_only_active_administrator_cannot_deactivate_their_person_record(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, email: 'admin@ctjj.org');

        $this->actingAs($admin)
            ->put(route('people.update', $admin->person), $this->personPayload([
                'institutional_email' => $admin->person->institutional_email,
                'active' => '0',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors(['active']);

        $this->assertTrue($admin->person->refresh()->active);
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
            'phone' => '(65) 99999-0000',
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
            'phone' => '(65) 99999-0000',
            'active' => true,
            'profile_completed_at' => now(),
        ]);
    }
}
