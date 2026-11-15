<?php

namespace Tests\Feature;

use App\Livewire\ScreenListing;
use App\Models\AuditLog;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Support\AuditLogGroupIndex;
use App\Support\AuditLogGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'administrador', ?School $school = null): User
    {
        $person = Person::create(['full_name' => 'Pessoa do desempenho', 'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Mãe', 'address' => 'Rua', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000',
            'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $school?->id, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }

    private function log(User $user, ?School $school = null, string $action = 'updated'): AuditLog
    {
        return AuditLog::create(['actor_user_id' => $user->id, 'school_id' => $school?->id,
            'auditable_type' => 'performance-test', 'auditable_id' => 1, 'action' => $action]);
    }

    private function assertIndexMatchesSource(User $user): void
    {
        $canonical = AuditLogGroups::groups($user)->orderBy('first_id')->get()
            ->map(fn ($row) => [(int) $row->first_id, (int) $row->last_id, (int) $row->group_count])->all();
        $indexed = AuditLogGroups::query($user)->select('audit_groups.first_id', 'audit_groups.last_id', 'audit_groups.group_count')
            ->orderBy('audit_groups.first_id')->get()
            ->map(fn ($row) => [(int) $row->first_id, (int) $row->last_id, (int) $row->group_count])->all();
        $this->assertSame($canonical, $indexed);
    }

    public function test_incremental_groups_preserve_exact_boundaries_and_school_visibility(): void
    {
        $a = School::create(['name' => 'Permitida']);
        $b = School::create(['name' => 'Outra escola']);
        $admin = $this->user();
        $manager = $this->user('gestor', $a);
        $this->log($admin, $a);
        $this->assertIndexMatchesSource($admin);
        $this->assertIndexMatchesSource($manager);
        $this->log($admin, $b);
        $this->log($admin, $a);
        $this->assertIndexMatchesSource($admin);
        $this->assertIndexMatchesSource($manager);
        $managerGroups = AuditLogGroups::query($manager)->where('auditable_type', 'performance-test')
            ->pluck('audit_groups.group_count')->map(fn ($count) => (int) $count)->all();
        $this->assertSame([2], $managerGroups);
        $this->log($admin, $a, 'created');
        $this->travel(6)->minutes();
        $this->log($admin, $a, 'created');
        $this->assertIndexMatchesSource($admin);
        $this->assertIndexMatchesSource($manager);
    }

    public function test_last_group_continues_across_index_checkpoints_without_losing_original_rows(): void
    {
        $admin = $this->user();
        $first = $this->log($admin);
        $this->assertIndexMatchesSource($admin);
        for ($i = 0; $i < 36; $i++) {
            $last = $this->log($admin);
        }
        $before = AuditLog::count();
        $group = AuditLogGroups::query($admin)->where('auditable_type', 'performance-test')
            ->select('audit_logs.*', 'audit_groups.first_id', 'audit_groups.group_count')->firstOrFail();
        $this->assertSame($last->id, $group->id);
        $this->assertSame($first->id, (int) $group->first_id);
        $this->assertSame(37, (int) $group->group_count);
        $this->assertSame($before, AuditLog::count());
    }

    public function test_warm_pagination_and_filters_do_not_recalculate_historical_windows(): void
    {
        $admin = $this->user();
        for ($i = 0; $i < 30; $i++) {
            $this->log($admin, action: $i % 2 ? 'created' : 'updated');
        }
        app(AuditLogGroupIndex::class)->ensure($admin);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $page = AuditLogGroups::query($admin)->where('auditable_type', 'performance-test')->where('action', 'created')->orderByDesc('audit_logs.id')
            ->select('audit_logs.id')->paginate(10);
        $this->assertSame(15, $page->total());
        $this->assertCount(10, $page);
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains(strtolower($sql), 'lag(')));
    }

    public function test_source_edits_deletions_and_foreign_key_changes_invalidate_the_projection(): void
    {
        $school = School::create(['name' => 'Escola']);
        $admin = $this->user();
        $first = $this->log($admin, $school);
        $second = $this->log($admin, $school);
        $this->assertIndexMatchesSource($admin);
        $first->forceFill(['created_at' => now()->subHour()])->save();
        $this->assertIndexMatchesSource($admin);
        $second->delete();
        $this->assertIndexMatchesSource($admin);
        $school->delete();
        $this->assertIndexMatchesSource($admin);
    }

    public function test_deleting_legacy_attendance_context_rebuilds_the_same_groups_as_the_source(): void
    {
        $admin = $this->user();
        $school = School::create(['name' => 'Escola do contexto']);
        $year = $school->academicYears()->create(['name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $period = $year->periods()->create(['name' => 'Período', 'position' => 1, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz', 'stage' => 'Ensino Médio']);
        $component = $course->components()->create(['name' => 'Matemática']);
        $class = $year->classes()->create(['name' => 'Turma']);
        $enrollment = $class->enrollments()->create(['person_id' => $admin->person_id, 'enrolled_at' => '2026-01-01']);
        $record = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2026-03-01', 'lesson_count' => 1]);
        $entry = DiaryAttendanceEntry::create(['diary_attendance_record_id' => $record->id, 'student_enrollment_id' => $enrollment->id, 'status' => 'present']);
        for ($i = 0; $i < 2; $i++) {
            AuditLog::create(['auditable_type' => DiaryAttendanceEntry::class, 'auditable_id' => $entry->id, 'action' => 'updated']);
        }
        $this->assertIndexMatchesSource($admin);
        $record->delete();
        $this->assertIndexMatchesSource($admin);
    }

    public function test_failed_refresh_rolls_back_the_checkpoint_and_can_be_retried(): void
    {
        $admin = $this->user();
        $first = $this->log($admin);
        app(AuditLogGroupIndex::class)->ensure($admin);
        $scope = app(AuditLogGroupIndex::class)->scopeKey($admin);
        $checkpoint = DB::table('audit_log_group_states')->where('scope_key', $scope)->value('last_log_id');
        $this->log($admin);
        $fail = true;
        DB::listen(function ($query) use (&$fail): void {
            if ($fail && str_contains($query->sql, 'insert into "audit_log_group_rows"')) {
                $fail = false;
                throw new RuntimeException('Falha simulada na projeção');
            }
        });
        try {
            app(AuditLogGroupIndex::class)->ensure($admin);
            $this->fail('A falha simulada deveria interromper a atualização.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada na projeção', $exception->getMessage());
        }
        $this->assertSame($checkpoint, DB::table('audit_log_group_states')->where('scope_key', $scope)->value('last_log_id'));
        $this->assertDatabaseHas('audit_log_group_rows', ['scope_key' => $scope, 'last_id' => $first->id, 'group_count' => 1]);
        $this->assertIndexMatchesSource($admin);
    }

    public function test_audit_remains_available_before_the_index_migration(): void
    {
        $admin = $this->user();
        $this->log($admin);
        Schema::drop('audit_log_group_rows');
        Schema::drop('audit_log_group_states');
        $this->assertIndexMatchesSource($admin);
    }

    public function test_role_reads_are_reused_but_revocation_and_new_requests_take_effect(): void
    {
        $school = School::create(['name' => 'Escola']);
        $manager = $this->user('gestor', $school);
        $reads = 0;
        DB::listen(function ($query) use (&$reads): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "person_school_roles"')) {
                $reads++;
            }
        });
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($manager->canManageSchool($school->id));
            $this->assertSame([$school->id], $manager->visibleSchoolIds());
        }
        $this->assertSame(1, $reads);
        $role = $manager->person->schoolRoles()->firstOrFail();
        $role->update(['active' => false]);
        $this->assertFalse($manager->canManageSchool($school->id));
        $role->update(['active' => true]);
        $this->assertTrue($manager->canManageSchool($school->id));
        DB::table('person_school_roles')->where('id', $role->id)->update(['active' => false]);
        $this->app->instance('request', Request::create('/proxima-pagina'));
        $this->assertFalse($manager->canManageSchool($school->id));
    }

    public function test_initial_listing_fetches_once_and_reactive_updates_still_load_current_data(): void
    {
        $admin = $this->user();
        KnowledgeArea::create(['name' => 'Área Alfa', 'formation' => 'Formação Geral Básica']);
        $reads = 0;
        DB::listen(function ($query) use (&$reads): void {
            if (str_contains($query->sql, 'order by "name" asc') && str_contains($query->sql, 'from "knowledge_areas"')) {
                $reads++;
            }
        });
        $this->actingAs($admin)->get(route('knowledge-areas.index'))->assertOk()->assertSee('Área Alfa');
        $this->assertSame(1, $reads);
        KnowledgeArea::create(['name' => 'Área Beta', 'formation' => 'Formação Geral Básica']);
        Livewire::actingAs($admin)->test(ScreenListing::class, ['screen' => 'knowledge-areas'])
            ->set('filters.q', 'Beta')->assertSee('Área Beta')->assertDontSee('Área Alfa');
        $this->get(route('knowledge-areas.index', ['f' => ['q' => 'Beta']]))->assertOk()->assertSee('Área Beta')->assertDontSee('Área Alfa');
    }
}
