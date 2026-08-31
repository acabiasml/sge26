<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\CalendarDay;
use App\Models\IssuedDocument;
use App\Models\OfficialDocument;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
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
        $this->actingAs($admin)->get(route('data-quality.index'))->assertOk();
        $this->actingAs($admin)->get(route('official-documents.create'))->assertOk();
        $this->actingAs($admin)->get(route('announcements.index'))->assertOk();
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    }

    public function test_manager_sees_only_managed_schools_and_cannot_manage_global_registry(): void
    {
        $school = School::query()->create(['name' => 'Escola A']);
        $otherSchool = School::query()->create(['name' => 'Escola B']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id);

        $this->actingAs($manager)->get(route('schools.index'))->assertOk()
            ->assertSee('Escola A')
            ->assertDontSee('Escola B');
        $this->actingAs($manager)->get(route('schools.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('schools.edit', $school))->assertForbidden();
        $this->actingAs($manager)->get(route('schools.academic-years.index', $school))->assertOk();
        $this->actingAs($manager)->get(route('schools.academic-years.index', $otherSchool))->assertForbidden();
        $this->actingAs($manager)->get(route('people.index'))->assertOk();
        $this->actingAs($manager)->get(route('people.create'))->assertOk();
        $this->actingAs($manager)->get(route('official-documents.create'))->assertOk();
    }

    public function test_manager_can_emit_official_document_for_managed_school(): void
    {
        $school = School::query()->create($this->officialSchoolData(['name' => 'Escola A']));
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'documentos@ctjj.org');

        $this->actingAs($manager)
            ->post(route('official-documents.store'), [
                'school_id' => $school->id,
                'type' => OfficialDocument::TYPE_LETTER,
                'title' => 'Ofício de teste',
                'orientation' => 'portrait',
                'line_spacing' => '1.15',
                'content_html' => '<p style="text-align: center; color: red;">Conteúdo oficial <script>alert(1)</script><span style="font-family: &quot;DejaVu Serif&quot;; font-size: 14pt;">válido</span>.</p>',
            ])
            ->assertOk()
            ->assertHeader('content-disposition');

        $document = OfficialDocument::query()->with('issuedDocument')->firstOrFail();

        $this->assertSame($school->id, $document->school_id);
        $this->assertSame(1.15, (float) $document->line_spacing);
        $this->assertStringNotContainsString('script', $document->content_html);
        $this->assertStringContainsString('text-align: center', $document->content_html);
        $this->assertStringContainsString('font-family: DejaVu Serif', $document->content_html);
        $this->assertStringContainsString('font-size: 14pt', $document->content_html);
        $this->assertStringNotContainsString('color: red', $document->content_html);
        $this->assertDatabaseHas('issued_documents', [
            'id' => $document->issued_document_id,
            'type' => 'official-document',
            'school_id' => $school->id,
        ]);
        $this->assertInstanceOf(IssuedDocument::class, $document->issuedDocument);
    }

    public function test_manager_cannot_emit_official_document_for_other_school(): void
    {
        $managedSchool = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $otherSchool = School::query()->create(['name' => 'Escola B', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $managedSchool->id, 'documentos.outro@ctjj.org');

        $this->actingAs($manager)
            ->post(route('official-documents.store'), [
                'school_id' => $otherSchool->id,
                'type' => OfficialDocument::TYPE_NOTICE,
                'title' => 'Comunicado indevido',
                'orientation' => 'portrait',
                'line_spacing' => '1.5',
                'content_html' => '<p>Texto</p>',
            ])
            ->assertSessionHasErrors('school_id');

        $this->assertDatabaseMissing('official_documents', [
            'school_id' => $otherSchool->id,
        ]);
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
                'legal_name' => 'Centro Tecnico Juvenil de Jarudore',
                'cnpj' => '00.176.974/0001-20',
                'inep' => '51061716',
                'founded_at' => '1990-01-15',
                'phone' => '(66) 99613-6796',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
                'email' => 'ctjj.mt@gmail.com',
                'website' => 'https://ctjj.org',
                'letterhead_text' => 'Texto para papel timbrado.',
                'address' => 'Av. Sao Joao',
                'city' => 'Poxoreu',
                'state' => 'MT',
                'postal_code' => '78700-970',
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

    public function test_administrator_can_upload_school_logo_and_path_is_saved(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create([
            'name' => 'Liceu Pedagógico São Francisco de Assis',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('schools.update', $school), [
                'name' => $school->name,
                'legal_name' => 'Centro Tecnico Juvenil de Jarudore',
                'cnpj' => '00.176.974/0001-20',
                'inep' => '51061716',
                'founded_at' => '1990-10-04',
                'phone' => '(66) 99613-6796',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
                'email' => 'ctjj.mt@gmail.com',
                'website' => 'https://ctjj.org',
                'letterhead_text' => 'Credenciamento e autorizacao vigentes.',
                'address' => 'Av. Sao Joao',
                'district' => 'Jarudore',
                'number' => 's/n',
                'city' => 'Poxoreu',
                'state' => 'MT',
                'postal_code' => '78700-970',
                'active' => '1',
                'logo' => UploadedFile::fake()->image('logo-liceu.jpg', 120, 80),
            ])
            ->assertRedirect(route('schools.edit', $school));

        $school->refresh();

        $this->assertNotNull($school->logo_path);
        $this->assertStringStartsWith('brand/schools/', $school->logo_path);
        $this->assertTrue(File::exists(public_path($school->logo_path)));

        File::delete(public_path($school->logo_path));
    }

    public function test_administrator_can_delete_school_without_roles(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Sem Vínculos']);

        $this->actingAs($admin)
            ->delete(route('schools.destroy', $school))
            ->assertRedirect(route('schools.index'));

        $this->assertDatabaseMissing('schools', [
            'id' => $school->id,
        ]);
    }

    public function test_school_with_roles_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Com Vínculo']);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Vinculada',
            'institutional_email' => 'vinculada@ctjj.org',
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('schools.destroy', $school))
            ->assertRedirect(route('schools.index'));

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
        ]);
    }

    public function test_administrator_can_delete_person_without_roles(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Sem Vinculo',
            'institutional_email' => 'pessoa.sem.vinculo@ctjj.org',
        ]);

        $this->actingAs($admin)
            ->delete(route('people.destroy', $person))
            ->assertRedirect(route('people.index'));

        $this->assertDatabaseMissing('people', [
            'id' => $person->id,
        ]);
    }

    public function test_person_with_role_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Pessoa Vinculada']);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Com Vinculo',
            'institutional_email' => 'pessoa.com.vinculo@ctjj.org',
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('people.destroy', $person))
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
        ]);
    }

    public function test_administrator_can_update_person_role_period(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola do Vinculo']);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Com Periodo',
            'institutional_email' => 'pessoa.periodo@ctjj.org',
            'cpf' => '12345678901',
            'active' => true,
        ]);
        $role = $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => '2026-01-20',
            'ended_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('people.roles.update', [$person, $role]), [
                'school_id' => $school->id,
                'role' => PersonSchoolRole::ROLE_STUDENT,
                'active' => '1',
                'started_at' => '2026-02-03',
                'ended_at' => '2026-12-18',
            ])
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('person_school_roles', [
            'id' => $role->id,
            'started_at' => '2026-02-03 00:00:00',
            'ended_at' => '2026-12-18 00:00:00',
        ]);
    }

    public function test_administrator_can_update_administration_role_start_date(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $role = $admin->person->schoolRoles()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('people.roles.update', [$admin->person, $role]), [
                'school_id' => null,
                'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
                'active' => '1',
                'started_at' => '2026-01-01',
                'ended_at' => null,
            ])
            ->assertRedirect(route('people.show', $admin->person));

        $this->assertDatabaseHas('person_school_roles', [
            'id' => $role->id,
            'started_at' => '2026-01-01 00:00:00',
            'ended_at' => null,
        ]);
    }

    public function test_administrator_can_add_person_contact_without_creating_related_person(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = Person::query()->create([
            'full_name' => 'Estudante',
            'institutional_email' => 'aluno@ctjj.org',
        ]);

        $this->actingAs($admin)
            ->post(route('people.contacts.store', $student), [
                'name' => 'Responsavel Sem Acesso',
                'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
                'cpf' => '123.456.789-10',
                'nis' => '12345678901',
                'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
                'legal_guardian' => '1',
                'emergency_contact' => '1',
            ])
            ->assertRedirect(route('people.show', $student));

        $this->assertDatabaseHas('person_contacts', [
            'person_id' => $student->id,
            'name' => 'Responsavel Sem Acesso',
            'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
            'nis' => '12345678901',
            'legal_guardian' => true,
            'emergency_contact' => true,
        ]);

        $this->assertDatabaseMissing('people', [
            'full_name' => 'Responsavel Sem Acesso',
        ]);
    }

    public function test_administrator_can_delete_contact_from_person_without_roles(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Sem Vinculo',
            'institutional_email' => 'sem.vinculo@ctjj.org',
        ]);
        $contact = $person->contacts()->create([
            'name' => 'Contato Removivel',
            'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
        ]);

        $this->actingAs($admin)
            ->delete(route('people.contacts.destroy', [$person, $contact]))
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseMissing('person_contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_people_filter_combines_role_and_school_on_the_same_active_role(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña', 'active' => true]);

        $teacherAtLiceu = Person::query()->create([
            'full_name' => 'Docente do Liceu',
            'institutional_email' => 'docente.liceu@ctjj.org',
            'cpf' => '11122233344',
            'active' => true,
        ]);
        $teacherAtLiceu->schoolRoles()->create([
            'school_id' => $liceu->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $studentAtLiceuTeacherElsewhere = Person::query()->create([
            'full_name' => 'Estudante do Liceu Docente Fora',
            'institutional_email' => 'docente.fora@ctjj.org',
            'cpf' => '55566677788',
            'active' => true,
        ]);
        $studentAtLiceuTeacherElsewhere->schoolRoles()->create([
            'school_id' => $liceu->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => now()->subYear()->toDateString(),
        ]);
        $studentAtLiceuTeacherElsewhere->schoolRoles()->create([
            'school_id' => $laura->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.index', [
                'table-filters' => [
                    'situacao' => '1',
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'escola' => (string) $liceu->id,
                ],
            ]))
            ->assertOk()
            ->assertSee('Docente do Liceu')
            ->assertDontSee('Estudante do Liceu Docente Fora');
    }

    public function test_people_filter_can_select_inactive_or_ended_school_roles(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola dos Vínculos', 'active' => true]);

        $activeTeacher = Person::query()->create([
            'full_name' => 'Docente Com Vínculo Atual',
            'institutional_email' => 'docente.atual@ctjj.org',
            'cpf' => '11122233344',
            'active' => true,
        ]);
        $activeTeacher->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        $endedTeacher = Person::query()->create([
            'full_name' => 'Docente Com Vínculo Encerrado',
            'institutional_email' => 'docente.encerrado@ctjj.org',
            'cpf' => '55566677788',
            'active' => true,
        ]);
        $endedTeacher->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.index', [
                'table-filters' => [
                    'papel' => PersonSchoolRole::ROLE_TEACHER,
                    'escola' => (string) $school->id,
                    'vinculo' => 'inativos',
                ],
            ]))
            ->assertOk()
            ->assertSee('Docente Com Vínculo Encerrado')
            ->assertDontSee('Docente Com Vínculo Atual');
    }

    public function test_inactive_incomplete_people_do_not_appear_as_registration_pendencies(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola de Teste']);

        Person::query()->create([
            'full_name' => 'Pessoa Inativa Incompleta',
            'active' => false,
        ]);

        $activePerson = Person::query()->create([
            'full_name' => 'Pessoa Ativa Incompleta',
            'active' => false,
        ]);
        $activePerson->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index'))
            ->assertOk()
            ->assertSee('Pessoa Ativa Incompleta')
            ->assertDontSee('Pessoa Inativa Incompleta');
    }

    public function test_inactive_people_roles_and_contacts_do_not_appear_as_registration_pendencies(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola de Teste']);

        $inactivePerson = Person::query()->create([
            'full_name' => 'Pessoa Inativa Com Vínculo Pendente',
            'active' => false,
        ]);
        $inactivePerson->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => false,
            'started_at' => null,
        ]);
        $inactivePerson->contacts()->create([
            'name' => 'Responsável de Inativo Sem Contato',
            'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
            'legal_guardian' => true,
        ]);

        $activePerson = Person::query()->create([
            'full_name' => 'Pessoa Ativa Com Vínculo Pendente',
            'active' => true,
        ]);
        $activePerson->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => null,
        ]);
        $activePerson->contacts()->create([
            'name' => 'Responsável de Ativo Sem Contato',
            'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
            'legal_guardian' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index'))
            ->assertOk()
            ->assertSee('Pessoa Ativa Com Vínculo Pendente')
            ->assertSee('Responsável de Ativo Sem Contato')
            ->assertDontSee('Pessoa Inativa Com Vínculo Pendente')
            ->assertDontSee('Responsável de Inativo Sem Contato');
    }

    public function test_administrator_can_filter_registration_pendencies_by_school(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis']);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña']);

        $personAtLiceu = Person::query()->create([
            'full_name' => 'Pessoa Pendente Liceu',
            'active' => true,
        ]);
        $personAtLiceu->schoolRoles()->create([
            'school_id' => $liceu->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $personAtLaura = Person::query()->create([
            'full_name' => 'Pessoa Pendente Laura',
            'active' => true,
        ]);
        $personAtLaura->schoolRoles()->create([
            'school_id' => $laura->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index', ['school_id' => $liceu->id]))
            ->assertOk()
            ->assertSee('Pessoa Pendente Liceu')
            ->assertDontSee('Pessoa Pendente Laura');
    }

    public function test_conformity_center_filters_by_severity_and_exports_pdf(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Conferência']);

        $person = Person::query()->create([
            'full_name' => 'Pessoa Sem CPF',
            'active' => true,
        ]);
        $person->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index', ['school_id' => $school->id, 'severity' => 'danger']))
            ->assertOk()
            ->assertSee('Central única de conformidade')
            ->assertSee('Pessoa Sem CPF')
            ->assertSee('PDF da conferência');

        $this->actingAs($admin)
            ->get(route('data-quality.pdf', ['school_id' => $school->id, 'severity' => 'danger']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'data-quality-compliance-report',
            'person_id' => $admin->person_id,
            'school_id' => $school->id,
        ]);
    }

    public function test_conformity_accepts_student_without_cpf_when_parent_has_cpf(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Conferência']);
        $student = Person::query()->create([
            'full_name' => 'Estudante Sem CPF Próprio',
            'birth_date' => now()->subYears(12),
            'birth_city' => 'Rondonópolis',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Mãe Identificada',
            'address' => 'Rua Um',
            'city' => 'Rondonópolis',
            'state' => 'MT',
            'postal_code' => '78700000',
        ]);
        $student->schoolRoles()->create([
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);
        $student->contacts()->create([
            'name' => 'Mãe Identificada',
            'relationship_type' => PersonContact::TYPE_MOTHER,
            'cpf' => '12345678901',
            'legal_guardian' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index', ['school_id' => $school->id, 'severity' => 'danger']))
            ->assertOk()
            ->assertDontSee('Estudantes sem CPF próprio e sem CPF na filiação')
            ->assertDontSee('Profissionais ativos sem CPF');
    }

    public function test_conformity_flags_current_class_without_matrix_and_period_without_assessment(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Acadêmica']);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $period = AcademicPeriod::withoutEvents(fn () => AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'I Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-10',
            'position' => 1,
        ]));
        SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'starts_period_id' => $period->id,
            'ends_period_id' => $period->id,
            'name' => '6º Ano A',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index', ['school_id' => $school->id, 'severity' => 'danger']))
            ->assertOk()
            ->assertSee('Turmas ativas sem matriz vinculada')
            ->assertSee('Períodos sem avaliação configurada')
            ->assertSee('6º Ano A')
            ->assertSee('I Bimestre');
    }

    public function test_manager_only_sees_registration_pendencies_from_managed_school(): void
    {
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis']);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $liceu->id);

        $personAtLiceu = Person::query()->create([
            'full_name' => 'Pessoa Visível Gestão',
            'active' => true,
        ]);
        $personAtLiceu->schoolRoles()->create([
            'school_id' => $liceu->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $personAtLaura = Person::query()->create([
            'full_name' => 'Pessoa Oculta Gestão',
            'active' => true,
        ]);
        $personAtLaura->schoolRoles()->create([
            'school_id' => $laura->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('data-quality.index', ['school_id' => $laura->id]))
            ->assertOk()
            ->assertSee('Pessoa Visível Gestão')
            ->assertDontSee('Pessoa Oculta Gestão');
    }

    public function test_dashboard_shows_active_month_calendars_grouped_by_school_for_administrator(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña', 'active' => true]);

        $liceuYear = $this->activeAcademicYear($liceu, 'Educação Básica 2026');
        $lauraYear = $this->activeAcademicYear($laura, 'Ensino Médio 2026');

        $this->calendarDay($liceuYear, 'Aula regular');
        $this->calendarDay($lauraYear, 'Conselho de Classe');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Calendários letivos do mês')
            ->assertSee('Educação Básica 2026')
            ->assertSee('Liceu Pedagógico São Francisco de Assis')
            ->assertSee('Aula regular')
            ->assertSee('Ensino Médio 2026')
            ->assertSee('Escola Laura Vicuña')
            ->assertSee('Conselho de Classe');
    }

    public function test_dashboard_can_navigate_months_and_does_not_double_count_the_same_school_date(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $firstSchool = School::query()->create(['name' => 'Escola Um', 'active' => true]);
        $secondSchool = School::query()->create(['name' => 'Escola Dois', 'active' => true]);
        $firstYear = $this->activeAcademicYear($firstSchool, 'Calendário Um');
        $secondYear = $this->activeAcademicYear($secondSchool, 'Calendário Dois');
        $selectedMonth = now()->copy()->subMonth()->startOfMonth();

        foreach ([$firstYear, $secondYear] as $year) {
            CalendarDay::query()->create([
                'academic_year_id' => $year->id,
                'date' => $selectedMonth->toDateString(),
                'type' => CalendarDay::TYPE_SCHOOL_DAY,
                'counts_as_school_day' => true,
                'title' => 'Dia compartilhado',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('dashboard', ['calendar_month' => $selectedMonth->format('Y-m')]))
            ->assertOk()
            ->assertSee(ucfirst($selectedMonth->translatedFormat('F/Y')))
            ->assertSee('1 dia(s) letivo(s)')
            ->assertSee('Dia compartilhado');
    }

    public function test_technical_enrollment_can_create_an_independent_unified_history(): void
    {
        $school = School::query()->create([
            'name' => 'Escola Técnica',
            'city' => 'General Carneiro',
            'state' => 'MT',
            'active' => true,
        ]);
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id)->person;
        $year = $this->activeAcademicYear($school, 'Educação Profissional 2025-2026');
        $course = AcademicCourse::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Técnico em Móveis',
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'modality' => AcademicCourse::MODALITY_PROFESSIONAL_TECHNOLOGICAL,
            'class_hour_minutes' => 60,
            'workload_hours' => 0,
            'active' => true,
        ]);
        $class = SchoolClass::query()->create(['academic_year_id' => $year->id, 'name' => '2025-2026', 'active' => true]);
        $class->courses()->attach($course);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => now()->startOfYear(),
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        $this->actingAs($admin)
            ->get(route('student-histories.student', $student))
            ->assertOk()
            ->assertSee('Ensino Técnico Profissionalizante');

        $this->actingAs($admin)
            ->post(route('student-histories.unified', [$student, AcademicCourse::STAGE_TECHNICAL]))
            ->assertRedirect();

        $history = StudentAcademicHistory::query()
            ->where('person_id', $student->id)
            ->where('education_stage', AcademicCourse::STAGE_TECHNICAL)
            ->firstOrFail();
        $this->assertSame('Histórico Escolar - Ensino Técnico Profissionalizante', $history->title);
        $this->assertSame('General Carneiro-Mt', $history->issued_place);
        $this->assertDatabaseHas('student_academic_history_years', [
            'student_academic_history_id' => $history->id,
            'student_enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($admin)
            ->put(route('people.histories.update', [$student, $history]), [
                'school_id' => $school->id,
                'title' => $history->title,
                'stage' => $history->stage,
                'legal_basis' => $history->legal_basis,
                'issued_place' => $history->issued_place,
                'issued_date' => $history->issued_date->toDateString(),
                'active' => 1,
                'years' => [[
                    'source' => 'manual',
                    'year' => '2025',
                    'stage' => 'Ensino Técnico Profissionalizante',
                    'modality' => 'Educação Profissional',
                    'grade_phase' => '1º Módulo',
                    'school_name' => 'Escola Externa',
                    'city' => 'Poxoréu',
                    'state' => 'MT',
                    'country' => 'Brasil',
                    'transcript_mode' => 'detailed',
                    'final_result' => 'Aprovado',
                    'workload_hours' => '400',
                ]],
                'components' => [[
                    'formation' => 'Formação Técnica Profissional',
                    'knowledge_area' => 'Móveis',
                    'name' => 'Desenho Técnico',
                    'records' => [[
                        'score_label' => '8,0',
                    ]],
                ]],
            ])
            ->assertRedirect(route('people.histories.show', [$student, $history]));

        $this->assertDatabaseHas('student_academic_history_records', [
            'score_label' => '8,0',
        ]);
        $this->assertDatabaseHas('student_academic_history_years', [
            'student_academic_history_id' => $history->id,
            'source' => 'manual',
            'label' => '1º Módulo',
            'grade_phase' => '1º Módulo',
        ]);
    }

    public function test_student_cannot_start_history_for_a_stage_without_an_enrollment(): void
    {
        $school = School::query()->create(['name' => 'Escola Fundamental', 'active' => true]);
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id)->person;
        $year = $this->activeAcademicYear($school, 'Ensino Fundamental '.now()->year);
        $course = AcademicCourse::query()->create([
            'academic_year_id' => $year->id,
            'name' => '6º Ano',
            'stage' => AcademicCourse::STAGE_ELEMENTARY,
            'modality' => AcademicCourse::MODALITY_REGULAR,
            'class_hour_minutes' => 60,
            'workload_hours' => 0,
            'active' => true,
        ]);
        $class = SchoolClass::query()->create(['academic_year_id' => $year->id, 'name' => '6º Ano A', 'active' => true]);
        $class->courses()->attach($course);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => now()->startOfYear(),
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        $this->actingAs($admin)
            ->get(route('student-histories.student', $student))
            ->assertOk()
            ->assertSee('Esta etapa será liberada quando houver matrícula do estudante nela.');

        $this->actingAs($admin)
            ->post(route('student-histories.unified', [$student, AcademicCourse::STAGE_HIGH_SCHOOL]))
            ->assertSessionHasErrors('stage');

        $this->assertDatabaseMissing('student_academic_histories', [
            'person_id' => $student->id,
            'education_stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
        ]);

        $this->actingAs($admin)
            ->post(route('student-histories.unified', [$student, AcademicCourse::STAGE_ELEMENTARY]))
            ->assertRedirect();
    }

    public function test_student_life_view_hides_secretarial_actions_from_the_student(): void
    {
        $school = School::query()->create(['name' => 'Escola do Estudante', 'active' => true]);
        $studentUser = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id);
        $year = $this->activeAcademicYear($school, 'Educação Básica');
        $class = SchoolClass::query()->create(['academic_year_id' => $year->id, 'name' => '9º Ano', 'active' => true]);
        StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $studentUser->person_id,
            'enrolled_at' => now()->startOfYear(),
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($studentUser)
            ->get(route('people.student-map.show', $studentUser->person_id))
            ->assertOk()
            ->assertSee('Boletim')
            ->assertDontSee('Ficha individual');
    }

    public function test_dashboard_lists_all_birthdays_from_the_current_week(): void
    {
        $originalTimezone = date_default_timezone_get();
        Carbon::setTestNowAndTimezone('2026-07-20 10:00:00', 'America/Sao_Paulo');

        try {
            $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

            Person::query()->create([
                'full_name' => 'Aniversário Antigo do Mês',
                'birth_date' => '2000-07-01',
                'active' => true,
            ]);

            Person::query()->create([
                'full_name' => 'Aniversário da Semana Seguinte',
                'birth_date' => '2000-07-26',
                'active' => true,
            ]);

            foreach (range(19, 25) as $day) {
                Person::query()->create([
                    'full_name' => 'Aniversariante da Semana '.$day,
                    'birth_date' => sprintf('2000-07-%02d', $day),
                    'active' => true,
                ]);
            }

            $response = $this->actingAs($admin)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Aniversariantes desta semana')
                ->assertSee('19/07 a 25/07');

            $this->assertSame(2, substr_count($response->getContent(), 'Aniversário Antigo do Mês'));
            $this->assertSame(2, substr_count($response->getContent(), 'Aniversário da Semana Seguinte'));

            foreach (range(19, 25) as $day) {
                $response->assertSee('Aniversariante da Semana '.$day);
                $this->assertSame(3, substr_count($response->getContent(), 'Aniversariante da Semana '.$day));
            }
        } finally {
            Carbon::setTestNow();
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_dashboard_limits_month_calendars_to_manager_schools(): void
    {
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $liceu->id);
        $manager->person->schoolRoles()->create([
            'school_id' => $laura->id,
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
        ]);

        $liceuYear = $this->activeAcademicYear($liceu, 'Calendário Liceu');
        $lauraYear = $this->activeAcademicYear($laura, 'Calendário Laura');

        $this->calendarDay($liceuYear, 'Dia do Liceu');
        $this->calendarDay($lauraYear, 'Dia da Laura');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Calendário Liceu')
            ->assertSee('Dia do Liceu')
            ->assertDontSee('Calendário Laura')
            ->assertDontSee('Dia da Laura');
    }

    public function test_dashboard_shows_month_calendars_linked_to_teacher_student_and_employee_roles(): void
    {
        $liceu = School::query()->create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'active' => true]);
        $laura = School::query()->create(['name' => 'Escola Laura Vicuña', 'active' => true]);
        $liceuYear = $this->activeAcademicYear($liceu, 'Calendário do Liceu');
        $lauraYear = $this->activeAcademicYear($laura, 'Calendário da Laura');

        $this->calendarDay($liceuYear, 'Dia letivo do Liceu');
        $this->calendarDay($lauraYear, 'Dia letivo da Laura');

        foreach ([PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT, PersonSchoolRole::ROLE_EMPLOYEE] as $role) {
            $user = $this->userWithRole($role, $liceu->id);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Calendário do Liceu')
                ->assertSee('Dia letivo do Liceu')
                ->assertDontSee('Calendário da Laura')
                ->assertDontSee('Dia letivo da Laura');
        }
    }

    public function test_inactive_incomplete_person_cannot_receive_role(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola Teste', 'active' => true]);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Inativa Sem Identidade',
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('people.roles.store', $person), [
                'school_id' => $school->id,
                'role' => PersonSchoolRole::ROLE_STUDENT,
                'active' => '1',
                'started_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('person');

        $this->assertDatabaseMissing('person_school_roles', [
            'person_id' => $person->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_inactive_incomplete_person_record_pdf_is_not_issued(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Pessoa Inativa Sem Documento',
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('people.pdf', $person))
            ->assertRedirect(route('people.show', $person))
            ->assertSessionHas('status', 'Não é possível emitir documento de pessoa inativa sem CPF.');

        $this->assertDatabaseMissing('issued_documents', [
            'person_id' => $person->id,
            'type' => 'person-record',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function officialSchoolData(array $overrides = []): array
    {
        static $sequence = 0;

        $sequence++;

        return array_merge([
            'name' => 'Escola Oficial '.$sequence,
            'legal_name' => 'Centro Tecnico Juvenil de Jarudore',
            'cnpj' => str_pad((string) $sequence, 14, '0', STR_PAD_LEFT),
            'inep' => str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'founded_at' => '1990-10-04',
            'phone' => '(66) 99613-6796',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'email' => 'ctjj.mt@gmail.com',
            'website' => 'https://ctjj.org',
            'letterhead_text' => 'Credenciamento e autorizacao vigentes.',
            'address' => 'Av. Sao Joao',
            'district' => 'Jarudore',
            'number' => 's/n',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-970',
            'active' => true,
        ], $overrides);
    }

    private function userWithRole(string $role, ?int $schoolId = null): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => str($role)->ascii()->slug()->value().'@ctjj.org',
            'cpf' => fake()->numerify('###########'),
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
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }

    private function activeAcademicYear(School $school, string $name): AcademicYear
    {
        return AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => $name,
            'reference_year' => now()->year,
            'starts_at' => now()->startOfYear()->toDateString(),
            'ends_at' => now()->endOfYear()->toDateString(),
            'minimum_school_days' => 200,
            'active' => true,
        ]);
    }

    private function calendarDay(AcademicYear $academicYear, string $title): CalendarDay
    {
        return CalendarDay::query()->create([
            'academic_year_id' => $academicYear->id,
            'date' => now()->startOfMonth()->toDateString(),
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
            'title' => $title,
        ]);
    }
}
