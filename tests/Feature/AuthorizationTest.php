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

    public function test_student_can_open_own_map_but_manager_cannot_open_student_from_another_school(): void
    {
        $studentSchool = School::query()->create(['name' => 'Escola do Estudante']);
        $otherSchool = School::query()->create(['name' => 'Outra Escola']);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $studentSchool->id);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $otherSchool->id);

        foreach ([$student, $manager] as $user) {
            $user->person->update([
                'cpf' => str_pad((string) $user->person_id, 11, '0', STR_PAD_LEFT),
                'birth_date' => '2000-01-01',
                'mother_name' => 'Mãe de teste',
                'phone' => '66999999999',
                'profile_completed_at' => now(),
            ]);
        }

        $this->actingAs($student)
            ->get(route('people.student-map.show', $student->person_id))
            ->assertOk()
            ->assertSee('Mapa do estudante');

        $this->actingAs($manager)
            ->get(route('people.student-map.show', $student->person_id))
            ->assertForbidden();
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

