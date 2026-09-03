<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DiaryAssessmentResult;
use App\Models\IssuedDocument;
use App\Livewire\AuditLogsTable;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_audited_with_old_and_new_values(): void
    {
        $person = Person::query()->create([
            'full_name' => 'Maria Silva',
            'institutional_email' => 'maria@ctjj.org',
        ]);

        $person->update([
            'full_name' => 'Maria Souza',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Person::class,
            'auditable_id' => $person->id,
            'action' => 'updated',
        ]);

        $audit = AuditLog::query()
            ->where('auditable_type', Person::class)
            ->where('auditable_id', $person->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('Maria Silva', $audit->old_values['full_name']);
        $this->assertSame('Maria Souza', $audit->new_values['full_name']);
    }

    public function test_model_changes_made_by_authenticated_user_store_the_actor(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Joao Pereira',
            'institutional_email' => 'joao@ctjj.org',
            'cpf' => fake()->unique()->numerify('###########'),
        ]);

        $this->actingAs($admin);

        $person->update([
            'full_name' => 'Joao Pedro Pereira',
        ]);

        $audit = AuditLog::query()
            ->where('auditable_type', Person::class)
            ->where('auditable_id', $person->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame($admin->person_id, $audit->actor_person_id);
        $this->assertSame(PersonSchoolRole::ROLE_ADMINISTRATOR, $audit->actor_role);
    }

    public function test_model_updates_continue_when_audit_log_creation_fails(): void
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Models\Concerns\Auditable;

            protected $guarded = [];

            public function getMorphClass(): string
            {
                return 'test-model';
            }

            public function getKey()
            {
                return 7;
            }

            protected function auditLogRepository(): Builder
            {
                return new class extends \Illuminate\Database\Eloquent\Builder {
                    public function __construct()
                    {
                    }

                    public function create(array $attributes = [])
                    {
                        throw new RuntimeException('db fail');
                    }
                };
            }
        };

        $method = new \ReflectionMethod($model, 'writeAuditLog');
        $method->setAccessible(true);

        $method->invoke($model, 'updated');

        $this->assertTrue(true);
    }

    public function test_audit_log_uses_brasilia_timezone_by_default(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        AuditLog::query()->forceCreate([
            'action' => 'updated',
            'auditable_type' => Person::class,
            'auditable_id' => 1,
            'created_at' => '2026-06-04 12:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Brasília')
            ->assertSee('America/Sao_Paulo')
            ->assertSee('04/06/2026 09:00');
    }

    public function test_administrator_can_change_audit_timezone(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        $this->actingAs($admin)
            ->patch(route('audit-logs.timezone.update'), [
                'audit_timezone' => 'UTC',
            ])
            ->assertRedirect(route('audit-logs.index'));

        $this->assertSame('UTC', $admin->refresh()->audit_timezone);
    }

    public function test_audit_table_shows_actor_and_record_identification(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Auditada']);

        AuditLog::query()->forceCreate([
            'actor_user_id' => $admin->id,
            'actor_person_id' => $admin->person_id,
            'school_id' => $school->id,
            'actor_role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'auditable_type' => School::class,
            'auditable_id' => $school->id,
            'action' => 'updated',
            'created_at' => now()->addMinute(),
        ]);

        Livewire::actingAs($admin)
            ->test(AuditLogsTable::class)
            ->assertSee($admin->person->full_name)
            ->assertSee('Cadastro alterado')
            ->assertSee('Escola (registro '.$school->id.')')
            ->assertSee('Escola Auditada');
    }

    public function test_audit_table_uses_human_labels_for_diary_grade_records(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Auditada']);

        AuditLog::query()->forceCreate([
            'actor_user_id' => $admin->id,
            'actor_person_id' => $admin->person_id,
            'school_id' => $school->id,
            'actor_role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'auditable_type' => DiaryAssessmentResult::class,
            'auditable_id' => 18352,
            'action' => 'created',
            'created_at' => now()->addMinute(),
        ]);

        Livewire::actingAs($admin)
            ->test(AuditLogsTable::class)
            ->assertSee('Nota de avaliação (registro 18352)')
            ->assertDontSee('DiaryAssessmentResult');
    }

    public function test_issued_document_audit_translates_fields_and_document_type(): void
    {
        $audit = new AuditLog([
            'auditable_type' => IssuedDocument::class,
            'auditable_id' => 386,
            'action' => 'created',
            'old_values' => [],
            'new_values' => [
                'type' => 'teacher-diary',
                'verification_code' => 'BEABA-TESTE',
                'person_id' => 606,
                'issued_by_user_id' => 18,
                'payload' => ['title' => 'Diário de classe'],
            ],
        ]);

        $changes = \App\Support\AuditLogPresenter::changes($audit);

        $this->assertSame('Diário de classe', \App\Support\AuditLogPresenter::recordLabel($audit));
        $this->assertSame('Diário de classe', \App\Support\AuditLogPresenter::value('teacher-diary', 'type', IssuedDocument::class));
        $this->assertSame(
            ['Tipo', 'Código de verificação', 'Pessoa', 'Emitido pelo usuário', 'Dados complementares'],
            collect($changes)->pluck('field')->all(),
        );

        $student = Person::query()->create(['full_name' => 'Maria da Silva']);
        $audit->new_values = ['type' => 'student-academic-history', 'person_id' => $student->id];

        $this->assertSame('Histórico escolar do estudante Maria da Silva', \App\Support\AuditLogPresenter::recordLabel($audit));

        app()->setLocale('it');

        $this->assertSame('Registrazione creata', \App\Support\AuditLogPresenter::actionLabel('created'));
        $this->assertSame('Carriera scolastica dello studente Maria da Silva', \App\Support\AuditLogPresenter::recordLabel($audit));
    }

    public function test_non_administrator_cannot_change_audit_timezone(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id);

        $this->actingAs($manager)
            ->patch(route('audit-logs.timezone.update'), [
                'audit_timezone' => 'UTC',
            ])
            ->assertForbidden();

        $this->assertNull($manager->refresh()->audit_timezone);
    }

    private function userWithRole(string $role, ?int $schoolId = null): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => str($role)->ascii()->slug()->value().fake()->unique()->numberBetween(100, 999).'@ctjj.org',
            'cpf' => fake()->unique()->numerify('###########'),
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
            'profile_completed_at' => now(),
        ]);

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
}
