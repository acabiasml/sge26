<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_main_admin_screens(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('schools.index'))->assertOk();
        $this->actingAs($admin)->get(route('schools.create'))->assertOk();
        $this->actingAs($admin)->get(route('people.index'))->assertOk();
        $this->actingAs($admin)->get(route('people.create'))->assertOk();
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    }

    public function test_manager_cannot_manage_global_school_registry(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id);

        $this->actingAs($manager)->get(route('schools.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('people.index'))->assertOk();
        $this->actingAs($manager)->get(route('people.create'))->assertOk();
    }

    public function test_school_state_must_be_a_valid_brazilian_state(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        $this->actingAs($admin)
            ->post(route('schools.store'), [
                'name' => 'Escola Teste',
                'cnpj' => '12.345.678/0001-90',
                'state' => 'XX',
                'active' => '1',
            ])
            ->assertSessionHasErrors('state');

        $this->assertDatabaseMissing('schools', [
            'name' => 'Escola Teste',
        ]);
    }

    public function test_administrator_can_save_school_institutional_data(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        $this->actingAs($admin)
            ->post(route('schools.store'), [
                'name' => 'Escola Institucional',
                'founded_at' => '1990-01-15',
                'website' => 'https://ctjj.org',
                'letterhead_text' => 'Texto para papel timbrado.',
                'state' => 'MT',
                'active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'name' => 'Escola Institucional',
            'founded_at' => '1990-01-15 00:00:00',
            'website' => 'https://ctjj.org',
            'letterhead_text' => 'Texto para papel timbrado.',
        ]);
    }

    public function test_administrator_can_add_person_relationship(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = Person::query()->create([
            'full_name' => 'Estudante',
            'institutional_email' => 'estudante@ctjj.org',
        ]);
        $guardian = Person::query()->create([
            'full_name' => 'Responsavel',
            'institutional_email' => 'responsavel@ctjj.org',
            'phone' => '(65) 99999-1234',
        ]);

        $this->actingAs($admin)
            ->post(route('people.relationships.store', $student), [
                'related_person_id' => $guardian->id,
                'relationship_type' => PersonRelationship::TYPE_LEGAL_GUARDIAN,
                'legal_guardian' => '1',
                'emergency_contact' => '1',
                'notes' => 'Contato principal.',
            ])
            ->assertRedirect(route('people.show', $student));

        $this->assertDatabaseHas('person_relationships', [
            'person_id' => $student->id,
            'related_person_id' => $guardian->id,
            'relationship_type' => PersonRelationship::TYPE_LEGAL_GUARDIAN,
            'legal_guardian' => true,
            'emergency_contact' => true,
        ]);
    }

    private function userWithRole(string $role, ?int $schoolId = null): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => str($role)->ascii()->slug()->value().'@ctjj.org',
            'cpf' => fake()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'active' => true,
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }
}
