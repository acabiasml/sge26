<?php

namespace Tests\Feature;

use App\Models\Person;
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
