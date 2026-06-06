<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->actingAs($admin)->get(route('academic-years.index'))->assertOk();
        $this->actingAs($admin)->get(route('academic-years.create'))->assertOk();
        $this->actingAs($admin)->get(route('calendar-events.index'))->assertOk();
        $this->actingAs($admin)->get(route('announcements.index'))->assertOk();
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
                'phone' => '(65) 99999-0000',
                'legal_guardian' => '1',
                'emergency_contact' => '1',
            ])
            ->assertRedirect(route('people.show', $student));

        $this->assertDatabaseHas('person_contacts', [
            'person_id' => $student->id,
            'name' => 'Responsavel Sem Acesso',
            'relationship_type' => PersonContact::TYPE_LEGAL_GUARDIAN,
            'legal_guardian' => true,
            'emergency_contact' => true,
        ]);

        $this->assertDatabaseMissing('people', [
            'full_name' => 'Responsavel Sem Acesso',
        ]);
    }

    public function test_inactive_incomplete_people_do_not_appear_as_registration_pendencies(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        Person::query()->create([
            'full_name' => 'Pessoa Inativa Incompleta',
            'active' => false,
        ]);

        Person::query()->create([
            'full_name' => 'Pessoa Ativa Incompleta',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('data-quality.index'))
            ->assertOk()
            ->assertSee('Pessoa Ativa Incompleta')
            ->assertDontSee('Pessoa Inativa Incompleta');
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
            ->assertSessionHas('status', 'Não é possível emitir documento de pessoa inativa sem CPF e e-mail institucional.');

        $this->assertDatabaseMissing('issued_documents', [
            'person_id' => $person->id,
            'type' => 'person-record',
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
