<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleViewPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_active_roles_can_switch_views_and_permissions(): void
    {
        $managerSchool = School::query()->create(['name' => 'Escola de Gestão']);
        $teacherSchool = School::query()->create(['name' => 'Escola de Docência']);
        $person = $this->person();
        foreach ([
            [PersonSchoolRole::ROLE_ADMINISTRATOR, null],
            [PersonSchoolRole::ROLE_MANAGER, $managerSchool->id],
            [PersonSchoolRole::ROLE_TEACHER, $teacherSchool->id],
        ] as [$role, $schoolId]) {
            $person->schoolRoles()->create(['role' => $role, 'school_id' => $schoolId, 'active' => true]);
        }
        $user = User::factory()->create(['person_id' => $person->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Visão atual: Administração')
            ->assertSee('value="administrador"', false)
            ->assertSee('value="gestor"', false)
            ->assertSee('value="professor"', false);

        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_TEACHER])
            ->assertRedirect(route('dashboard'));
        $this->assertSame(PersonSchoolRole::ROLE_TEACHER, $user->fresh()->preferred_role_view);
        $this->assertSame([$teacherSchool->id], $user->visibleSchoolIds());
        $this->get(route('dashboard'))->assertOk()->assertSee('Visão atual: Docência')->assertDontSee('Gestão escolar');
        $this->get(route('schools.create'))->assertForbidden();
        $this->get(route('people.index'))->assertForbidden();
        $this->get(route('teacher-diaries.index'))->assertOk()->assertViewHas('isManagement', false);
        $this->get(route('teacher-schedules.index'))->assertOk();

        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_MANAGER])
            ->assertRedirect(route('dashboard'));
        $this->assertSame([$managerSchool->id], $user->visibleSchoolIds());
        $this->assertSame([$managerSchool->id], $user->manageableSchoolIds());
        $this->get(route('schools.create'))->assertForbidden();
        $this->get(route('people.index'))->assertOk();
        $this->get(route('teacher-schedules.index'))->assertForbidden();

        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_ADMINISTRATOR])
            ->assertRedirect(route('dashboard'));
        $this->get(route('schools.create'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Visão atual: Administração');
    }

    public function test_only_active_roles_can_be_selected_and_stale_preference_falls_back(): void
    {
        $person = $this->person();
        $manager = $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_MANAGER, 'active' => true]);
        $teacher = $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_TEACHER, 'active' => true]);
        $person->schoolRoles()->create([
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => now()->addDay()->toDateString(),
        ]);
        $user = User::factory()->create(['person_id' => $person->id]);

        $this->assertSame([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER], $user->availableViews());

        $this->actingAs($user)->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_ADMINISTRATOR])
            ->assertForbidden();
        $this->assertNull($user->fresh()->preferred_role_view);

        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_TEACHER])
            ->assertRedirect(route('dashboard'));
        $teacher->update(['active' => false]);
        $this->assertSame(PersonSchoolRole::ROLE_MANAGER, $user->fresh()->currentView());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="roleViewDropdown"', false);
        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_TEACHER])->assertForbidden();

        $manager->update(['ended_at' => now()->subDay()->toDateString()]);
        $this->assertSame([], $user->fresh()->availableViews());
    }

    public function test_student_diary_is_available_only_in_student_view(): void
    {
        $school = School::query()->create(['name' => 'Escola de Teste']);
        $person = $this->person();
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_MANAGER, 'school_id' => $school->id, 'active' => true]);
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_STUDENT, 'school_id' => $school->id, 'active' => true]);
        $user = User::factory()->create(['person_id' => $person->id]);

        $this->actingAs($user)->get(route('student-diaries.index'))->assertForbidden();
        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_STUDENT])
            ->assertRedirect(route('dashboard'));
        $this->get(route('student-diaries.index'))->assertOk();
        $this->get(route('people.index'))->assertForbidden();
    }

    public function test_teacher_view_cannot_open_own_student_map_without_switching_to_student(): void
    {
        $school = School::query()->create(['name' => 'Escola de Teste']);
        $person = $this->person();
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_TEACHER, 'school_id' => $school->id, 'active' => true]);
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_STUDENT, 'school_id' => $school->id, 'active' => true]);
        $user = User::factory()->create(['person_id' => $person->id]);

        $this->actingAs($user)->get(route('people.student-map.show', $person))->assertForbidden();
        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_STUDENT])
            ->assertRedirect(route('dashboard'));
        $this->get(route('people.student-map.show', $person))->assertOk();
    }

    public function test_guest_cannot_change_view(): void
    {
        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_MANAGER])
            ->assertRedirect(route('login'));
    }

    private function person(): Person
    {
        return Person::query()->create([
            'full_name' => 'Pessoa com múltiplos vínculos',
            'cpf' => fake()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria',
            'address' => 'Rua de Teste',
            'city' => 'Poxoréu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'profile_completed_at' => now(),
        ]);
    }
}
