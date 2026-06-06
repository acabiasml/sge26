<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_report_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', 'schools'))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertStringStartsWith('BEABA-', $document->verification_code);

        $this->get(route('documents.verify', $document->verification_code))
            ->assertOk()
            ->assertSee($document->verification_code)
            ->assertSee('Relatório emitido pelo sistema')
            ->assertSee('Documento válido');
    }

    public function test_public_document_verification_form_can_be_opened(): void
    {
        $this->get(route('documents.verify.form'))
            ->assertOk()
            ->assertSee('Verificar documento')
            ->assertSee('Código de verificação');
    }

    public function test_public_document_verification_lookup_redirects_to_code(): void
    {
        $this->post(route('documents.verify.lookup'), [
            'code' => 'beaba-abcd-efgh-ijkl',
        ])
            ->assertRedirect(route('documents.verify', 'BEABA-ABCD-EFGH-IJKL'));
    }

    public function test_excel_report_can_be_downloaded(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.excel', 'schools'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_pdf_report_uses_current_table_filters(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola Ativa', 'active' => true]);
        School::query()->create(['name' => 'Escola Inativa', 'active' => false]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'schools',
                'table-filters' => [
                    'situacao' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame('1', $document->payload['filters']['situacao']);
    }

    public function test_school_report_accepts_accented_status_filter_key(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola Ativa', 'active' => true]);
        School::query()->create(['name' => 'Escola Inativa', 'active' => false]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'schools',
                'table-filters' => [
                    'situação' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame('1', $document->payload['filters']['situacao']);
    }

    public function test_people_report_accepts_accented_status_filter_key(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $activePerson = Person::query()->create([
            'full_name' => 'Pessoa Ativa',
            'institutional_email' => 'ativa@ctjj.org',
            'cpf' => '11122233344',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
            'active' => true,
        ]);
        $inactivePerson = Person::query()->create([
            'full_name' => 'Pessoa Inativa',
            'institutional_email' => 'inativa@ctjj.org',
            'cpf' => '55566677788',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
            'active' => false,
        ]);

        foreach ([$activePerson, $inactivePerson] as $person) {
            $person->schoolRoles()->create([
                'school_id' => $school->id,
                'role' => PersonSchoolRole::ROLE_TEACHER,
                'active' => true,
                'started_at' => now()->subMonth()->toDateString(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'people',
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'situação' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame('1', $document->payload['filters']['situacao']);
    }

    public function test_roles_pdf_report_uses_current_table_filters(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Gestora',
            'institutional_email' => 'gestao@ctjj.org',
            'cpf' => '99988877766',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'position' => PersonSchoolRole::POSITION_DIRECTOR,
            'active' => true,
            'started_at' => '2026-01-01',
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => '2026-01-01',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'roles',
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_MANAGER,
                    'situacao' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame(PersonSchoolRole::ROLE_MANAGER, $document->payload['filters']['papel']);
    }

    public function test_roles_report_active_filter_excludes_expired_roles(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Docente',
            'institutional_email' => 'docente@ctjj.org',
            'cpf' => '99988877766',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
            'ended_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'roles',
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'escola' => (string) $school->id,
                    'situacao' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
    }

    public function test_roles_report_accepts_accented_status_filter_key(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Docente',
            'institutional_email' => 'docente-acento@ctjj.org',
            'cpf' => '12312312399',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'roles',
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'escola' => (string) $school->id,
                    'situação' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame('1', $document->payload['filters']['situacao']);
    }

    public function test_roles_report_exports_all_filtered_results_without_page_limit(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        foreach (range(1, 12) as $index) {
            $person = Person::query()->create([
                'full_name' => 'Docente '.$index,
                'institutional_email' => 'docente'.$index.'@ctjj.org',
                'cpf' => fake()->unique()->numerify('###########'),
                'birth_date' => '1990-01-01',
                'phone' => '(65) 99999-0000',
                'profile_completed_at' => now(),
            ]);

            $person->schoolRoles()->create([
                'school_id' => $school->id,
                'role' => PersonSchoolRole::ROLE_TEACHER,
                'active' => true,
                'started_at' => now()->subMonth()->toDateString(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'roles',
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'escola' => (string) $school->id,
                    'situacao' => '1',
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(12, $document->payload['rows_count']);
    }

    public function test_audit_report_accepts_accented_action_filter_key(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        AuditLog::query()->create([
            'school_id' => $school->id,
            'auditable_type' => School::class,
            'auditable_id' => $school->id,
            'action' => 'created',
        ]);
        AuditLog::query()->create([
            'school_id' => $school->id,
            'auditable_type' => School::class,
            'auditable_id' => $school->id,
            'action' => 'updated',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', [
                'type' => 'audit-logs',
                'table-filters' => [
                    'ação' => 'updated',
                    'escola' => (string) $school->id,
                ],
            ]))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertSame('updated', $document->payload['filters']['acao']);
    }

    public function test_school_record_pdf_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('schools.pdf', $school))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'school-record')
            ->firstOrFail();

        $this->assertSame($school->id, $document->school_id);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    public function test_person_record_pdf_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Maria Silva',
            'institutional_email' => 'maria@ctjj.org',
            'cpf' => '11122233344',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.pdf', $person))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'person-record')
            ->firstOrFail();

        $this->assertSame($person->id, $document->person_id);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);

        $this->get(route('documents.verify', $document->verification_code))
            ->assertOk()
            ->assertSee('Ficha de cadastro de pessoa')
            ->assertDontSee('person-record');
    }

    public function test_global_administrator_person_record_uses_maintainer_letterhead(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);

        $admin->person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'position' => PersonSchoolRole::POSITION_COORDINATOR,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.pdf', $admin->person))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'person-record')
            ->firstOrFail();

        $this->assertNull($document->school_id);
    }

    public function test_person_record_uses_school_letterhead_when_person_has_only_school_role(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Gestora',
            'institutional_email' => 'pessoa-gestora@ctjj.org',
            'cpf' => '33322211100',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'position' => PersonSchoolRole::POSITION_COORDINATOR,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.pdf', $person))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'person-record')
            ->firstOrFail();

        $this->assertSame($school->id, $document->school_id);
    }

    private function userWithRole(string $role): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => str($role)->ascii()->slug()->value().'@ctjj.org',
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
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
