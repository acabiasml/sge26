<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_schools_people_and_roles_in_any_school(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        $this->assertTrue(Gate::forUser($administrator)->allows('manage-schools'));
        $this->assertTrue(Gate::forUser($administrator)->allows('manage-school', $school->id));
        $this->assertTrue(Gate::forUser($administrator)->allows('manage-people', $school->id));
        $this->assertTrue(Gate::forUser($administrator)->allows('assign-roles', $school->id));
    }

    public function test_manager_can_only_manage_people_and_roles_in_their_own_active_school(): void
    {
        $ownSchool = School::query()->create(['name' => 'Escola A']);
        $otherSchool = School::query()->create(['name' => 'Escola B']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $ownSchool->id);

        $this->assertFalse(Gate::forUser($manager)->allows('manage-schools'));
        $this->assertTrue(Gate::forUser($manager)->allows('manage-school', $ownSchool->id));
        $this->assertTrue(Gate::forUser($manager)->allows('manage-people', $ownSchool->id));
        $this->assertTrue(Gate::forUser($manager)->allows('assign-roles', $ownSchool->id));
        $this->assertFalse(Gate::forUser($manager)->allows('manage-school', $otherSchool->id));
        $this->assertFalse(Gate::forUser($manager)->allows('manage-people', $otherSchool->id));
        $this->assertFalse(Gate::forUser($manager)->allows('assign-roles', $otherSchool->id));
    }

    public function test_expired_manager_role_does_not_authorize_school_management(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $manager = $this->userWithRole(
            PersonSchoolRole::ROLE_MANAGER,
            $school->id,
            endedAt: now()->subDay()->toDateString(),
        );

        $this->assertFalse($manager->isManager());
        $this->assertFalse(Gate::forUser($manager)->allows('manage-school', $school->id));
        $this->assertFalse(Gate::forUser($manager)->allows('assign-roles', $school->id));
    }

    public function test_student_cannot_manage_people_or_roles(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id);

        $this->assertFalse(Gate::forUser($student)->allows('manage-schools'));
        $this->assertFalse(Gate::forUser($student)->allows('manage-school', $school->id));
        $this->assertFalse(Gate::forUser($student)->allows('manage-people', $school->id));
        $this->assertFalse(Gate::forUser($student)->allows('assign-roles', $school->id));
    }

    private function userWithRole(string $role, ?int $schoolId = null, ?string $endedAt = null): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => Str::of($role)->ascii()->slug()->value().'@ctjj.org',
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
            'ended_at' => $endedAt,
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }
}
