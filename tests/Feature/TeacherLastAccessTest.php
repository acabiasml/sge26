<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordUserLastAccess;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\UserLastAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TeacherLastAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(16, 0));
    }

    public function test_login_and_subsequent_navigation_record_access_without_profile_audit_noise(): void
    {
        $school = School::create(['name' => 'Escola A']);
        $user = $this->account('Docente A', PersonSchoolRole::ROLE_TEACHER, $school);
        $updatedAt = $user->updated_at;
        $auditCount = DB::table('audit_logs')->count();

        Auth::login($user);
        $this->assertTrue($user->fresh()->last_access_at->equalTo(now()));
        $this->travel(2)->hours();
        $this->get(route('dashboard'))->assertOk();
        $this->assertTrue($user->fresh()->last_access_at->equalTo(now()));
        $this->assertTrue($user->fresh()->updated_at->equalTo($updatedAt));
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertArrayNotHasKey('last_access_at', $user->fresh()->toArray());
        $this->assertFalse($user->isFillable('last_access_at'));
    }

    public function test_static_assets_head_requests_and_guests_do_not_refresh_access_but_livewire_does(): void
    {
        $user = $this->account('Docente A', PersonSchoolRole::ROLE_TEACHER, School::create(['name' => 'Escola A']));
        $old = now()->subDays(8);
        $user->forceFill(['last_access_at' => $old])->save();
        foreach ([['GET', 'Vendor\\AssetsController@show', 'assets'], ['HEAD', 'App\\Http\\Controllers\\DashboardController', 'dashboard']] as [$method, $action, $name]) {
            $this->middlewareRequest($user, $method, $action, $name);
            $this->assertTrue($user->fresh()->last_access_at->equalTo($old));
        }
        $this->middlewareRequest(null, 'GET', 'App\\Http\\Controllers\\DashboardController', 'dashboard');
        $this->assertTrue($user->fresh()->last_access_at->equalTo($old));
        $this->middlewareRequest($user, 'POST', 'Livewire\\Mechanisms\\HandleRequests\\HandleRequests@handleUpdate', 'app.livewire.update');
        $this->assertTrue($user->fresh()->last_access_at->equalTo(now()));
    }

    public function test_older_concurrent_access_cannot_replace_a_newer_timestamp(): void
    {
        $user = User::factory()->create();
        $latest = now()->addMinute();
        DB::table('users')->where('id', $user->id)->update(['last_access_at' => $latest]);
        app(UserLastAccess::class)->record($user);
        $this->assertTrue($user->fresh()->last_access_at->equalTo($latest));
    }

    public function test_management_only_sees_its_current_teachers_and_allowed_school_names(): void
    {
        $own = School::create(['name' => 'Escola Visível']);
        $other = School::create(['name' => 'Escola Restrita']);
        $manager = $this->account('Gestora', PersonSchoolRole::ROLE_MANAGER, $own);
        $shared = $this->account('Docente Compartilhada', PersonSchoolRole::ROLE_TEACHER, $own);
        $shared->person->schoolRoles()->create(['school_id' => $other->id, 'role' => PersonSchoolRole::ROLE_TEACHER, 'active' => true]);
        $this->account('Docente Restrita', PersonSchoolRole::ROLE_TEACHER, $other);
        foreach ([['active' => false], ['ended_at' => now()->subDay()], ['started_at' => now()->addDay()]] as $index => $values) {
            $inactive = $this->account('Docente Inativa '.$index, PersonSchoolRole::ROLE_TEACHER, $own);
            $inactive->person->schoolRoles()->first()->update($values);
        }
        $response = $this->actingAs($manager)->get(route('dashboard'))->assertOk();
        $overview = $response->viewData('teacherLastAccess');
        $this->assertSame(1, $overview['summary']['total']);
        $this->assertSame(['Escola Visível'], $overview['teachers']->first()['schools']->all());
        $response->assertSee('Docente Compartilhada')->assertDontSee('Docente Restrita')->assertDontSee('Docente Inativa');
        $this->get(route('dashboard', ['teacher_access_school' => $other->id]))->assertForbidden();
    }

    public function test_seven_day_boundary_unknown_access_and_multiple_accounts_are_classified_correctly(): void
    {
        $school = School::create(['name' => 'Escola A']);
        $manager = $this->account('Gestora', PersonSchoolRole::ROLE_MANAGER, $school);
        $boundary = $this->account('A Limite', PersonSchoolRole::ROLE_TEACHER, $school);
        $boundary->forceFill(['last_access_at' => now()->subDays(7)])->save();
        $overdue = $this->account('B Atrasado', PersonSchoolRole::ROLE_TEACHER, $school);
        $overdue->forceFill(['last_access_at' => now()->subDays(7)->subSecond()])->save();
        $unknown = $this->account('C Sem registro', PersonSchoolRole::ROLE_TEACHER, $school);
        $noAccount = $this->account('D Sem conta', PersonSchoolRole::ROLE_TEACHER, $school);
        $noAccount->delete();
        $multi = $this->account('E Duas contas', PersonSchoolRole::ROLE_TEACHER, $school);
        $multi->forceFill(['last_access_at' => now()->subDays(9)])->save();
        User::factory()->create(['person_id' => $multi->person_id, 'last_access_at' => now()->subDay()]);
        $response = $this->actingAs($manager)->get(route('dashboard'))->assertOk();
        $overview = $response->viewData('teacherLastAccess');
        $this->assertSame(['total' => 5, 'recent' => 2, 'missing' => 2, 'overdue' => 1], $overview['summary']);
        $this->assertSame(['recent', 'overdue', 'missing', 'missing', 'recent'], $overview['teachers']->pluck('status')->all());
        $this->assertFalse($overview['teachers'][3]['has_account']);
        $this->assertTrue($overview['teachers'][4]['last_access_at']->equalTo(now()->subDay()));
        $response->assertSee('Sem conta')->assertSee('Último acesso (MT)')->assertSee('Mais de 7 dias');
        $this->get(route('dashboard', ['teacher_access_status' => 'overdue']))->assertOk()
            ->assertViewHas('teacherLastAccess', fn ($access) => $access['teachers']->total() === 1 && $access['teachers']->first()['person']->id === $overdue->person_id);
    }

    public function test_filters_sorting_and_ten_row_pagination_work_and_keep_panel_open(): void
    {
        $school = School::create(['name' => 'Escola A']);
        $other = School::create(['name' => 'Escola B']);
        $admin = $this->account('Admin', PersonSchoolRole::ROLE_ADMINISTRATOR, $school);
        foreach (range(12, 1) as $number) {
            $this->account(sprintf('Docente %02d', $number), PersonSchoolRole::ROLE_TEACHER, $school);
        }
        $this->account('Outro docente', PersonSchoolRole::ROLE_TEACHER, $other);
        $response = $this->actingAs($admin)->get(route('dashboard', ['teacher_access_school' => $school->id]))->assertOk();
        $teachers = $response->viewData('teacherLastAccess')['teachers'];
        $this->assertSame(12, $teachers->total());
        $this->assertCount(10, $teachers);
        $this->assertSame('Docente 01', $teachers->first()['person']->full_name);
        $response->assertSee('sge-teacher-access mb-4"  open', false);
        $this->get(route('dashboard', ['teacher_access_school' => $school->id, 'teacher_access_page' => 2]))->assertOk()
            ->assertViewHas('teacherLastAccess', fn ($access) => $access['teachers']->count() === 2 && $access['teachers']->first()['person']->full_name === 'Docente 11');
        $this->get(route('dashboard', ['teacher_access_search' => 'Outro']))->assertOk()
            ->assertViewHas('teacherLastAccess', fn ($access) => $access['teachers']->total() === 1);
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('teacherLastAccess', fn ($access) => $access['summary']['total'] === 13);
    }

    public function test_teacher_employee_and_student_views_cannot_access_management_panel(): void
    {
        $school = School::create(['name' => 'Escola A']);
        foreach ([PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_EMPLOYEE, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $user = $this->account('Pessoa '.$role, $role, $school);
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertViewHas('teacherLastAccess', null)->assertDontSee('id="teacher-access"', false);
        }
        $dual = $this->account('Gestora docente', PersonSchoolRole::ROLE_MANAGER, $school);
        $dual->person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_TEACHER, 'active' => true]);
        $this->actingAs($dual)->get(route('dashboard'))->assertOk()
            ->assertViewHas('teacherLastAccess', fn ($access) => $access['teachers']->first()['last_access_at']->equalTo(now()));
        $this->patch(route('role-view.update'), ['role_view' => PersonSchoolRole::ROLE_TEACHER])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('teacherLastAccess', null)->assertDontSee('id="teacher-access"', false);
    }

    public function test_italian_dashboard_translates_the_panel(): void
    {
        $school = School::create(['name' => 'Scuola A']);
        $manager = $this->account('Gestore', PersonSchoolRole::ROLE_MANAGER, $school);
        $manager->update(['locale' => 'it']);
        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertSee('Accesso dei docenti')->assertSee('Oltre 7 giorni')->assertDontSee('dashboard.access.');
    }

    public function test_deployment_interval_without_new_column_does_not_break_dashboard_or_tracking(): void
    {
        $school = School::create(['name' => 'Escola A']);
        $manager = $this->account('Gestora', PersonSchoolRole::ROLE_MANAGER, $school);
        $migration = require database_path('migrations/2026_11_05_000001_add_last_access_at_to_users_table.php');
        $migration->down();
        app(UserLastAccess::class)->record($manager);
        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertViewHas('teacherLastAccess', null);
    }

    private function account(string $name, string $role, School $school): User
    {
        $person = Person::create([
            'full_name' => $name, 'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT',
            'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste',
            'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700-000', 'profile_completed_at' => now(),
        ]);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => $role, 'active' => true]);

        return User::factory()->create(['name' => $name, 'person_id' => $person->id]);
    }

    private function middlewareRequest(?User $user, string $method, string $action, string $name): void
    {
        $request = Request::create('/probe', $method);
        $route = new Route($method, '/probe', ['uses' => $action]);
        $route->name($name);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        app(RecordUserLastAccess::class)->handle($request, fn () => new Response('ok'));
    }
}
