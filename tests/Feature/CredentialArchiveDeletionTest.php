<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CredentialArchiveDeletionTest extends TestCase
{
    use RefreshDatabase;

    public static function nonAdministrators(): array
    {
        $cases = [];
        foreach (IssuedDocument::CREDENTIAL_TYPES as $type) {
            foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_EMPLOYEE, PersonSchoolRole::ROLE_STUDENT] as $role) {
                $cases[$type.' '.$role] = [$type, $role];
            }
        }

        return $cases;
    }

    public static function credentialTypes(): array
    {
        return array_map(fn ($type) => [$type], IssuedDocument::CREDENTIAL_TYPES);
    }

    #[DataProvider('nonAdministrators')]
    public function test_non_administrators_cannot_delete_any_certificate_or_diploma(string $type, string $role): void
    {
        [$school, $document] = $this->document($type);
        $user = $this->user($school, $role);
        $this->actingAs($user)->delete(route('credential-archive.destroy', $document))->assertForbidden();
        $this->assertDatabaseHas('issued_documents', ['id' => $document->id]);
        $this->assertSame('%PDF-original-ficticio', Storage::disk('local')->get($document->file_path));
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => IssuedDocument::class, 'auditable_id' => $document->id, 'action' => 'deleted']);
    }

    #[DataProvider('credentialTypes')]
    public function test_administrator_can_delete_the_record_and_its_original_with_an_audit_trail(string $type): void
    {
        [$school, $document] = $this->document($type);
        $admin = $this->user($school, PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin)->delete(route('credential-archive.destroy', $document))
            ->assertRedirect(route('people.show', $document->person_id).'#section-certificados')->assertSessionHas('status');
        $this->assertDatabaseMissing('issued_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'auditable_type' => IssuedDocument::class, 'auditable_id' => $document->id, 'action' => 'deleted']);
        $this->get(route('documents.verify', $document->verification_code))->assertNotFound();
        $this->get(route('credential-archive.pdf', $document))->assertNotFound();
    }

    #[DataProvider('credentialTypes')]
    public function test_model_guard_also_prevents_deletion_outside_the_archive_controller(string $type): void
    {
        [$school, $document] = $this->document($type);
        $this->actingAs($this->user($school, PersonSchoolRole::ROLE_MANAGER));
        try {
            $document->delete();
            $this->fail('Uma pessoa sem Administração não pode excluir certificados.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('issued_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_delete_button_is_only_shown_to_administrators_and_managers_can_still_reprint(): void
    {
        [$school, $document] = $this->document('free-course-certificate');
        $person = $document->person->load('issuedDocuments');
        $manager = $this->user($school, PersonSchoolRole::ROLE_MANAGER);
        $this->actingAs($manager);
        $html = view('people.partials.credential-archive', compact('person'))->render();
        $this->assertStringNotContainsString(route('credential-archive.destroy', $document).'"', $html);
        $this->get(route('credential-archive.pdf', $document))->assertOk()->assertContent('%PDF-original-ficticio');
        $this->actingAs($this->user($school, PersonSchoolRole::ROLE_ADMINISTRATOR));
        $html = view('people.partials.credential-archive', compact('person'))->render();
        $this->assertStringContainsString(route('credential-archive.destroy', $document).'"', $html);
        $this->assertStringContainsString('data-confirm-message', $html);
    }

    public function test_even_an_administrator_cannot_use_this_endpoint_to_delete_other_document_types(): void
    {
        [$school, $document] = $this->document('person-record');
        $this->actingAs($this->user($school, PersonSchoolRole::ROLE_ADMINISTRATOR))->delete(route('credential-archive.destroy', $document))->assertNotFound();
        $this->assertDatabaseHas('issued_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_file_deletion_failure_rolls_back_the_record_and_the_deletion_audit(): void
    {
        [$school, $document] = $this->document('technical-course-diploma');
        $originalDisk = Storage::disk('local');
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with($document->file_path)->once()->andReturnTrue();
        $disk->shouldReceive('delete')->with($document->file_path)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->actingAs($this->user($school, PersonSchoolRole::ROLE_ADMINISTRATOR));
        $this->withoutExceptionHandling();
        try {
            $this->delete(route('credential-archive.destroy', $document));
            $this->fail('A falha de exclusão do arquivo deve cancelar a exclusão.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('A exclusão foi cancelada', $exception->getMessage());
        }
        $this->assertDatabaseHas('issued_documents', ['id' => $document->id]);
        $this->assertSame('%PDF-original-ficticio', $originalDisk->get($document->file_path));
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => IssuedDocument::class, 'auditable_id' => $document->id, 'action' => 'deleted']);
    }

    private function document(string $type): array
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
        Storage::fake('local');
        $school = School::create(['name' => 'Escola do arquivo']);
        $person = Person::create(['full_name' => 'Participante Fictício do Arquivo', 'active' => true]);
        $document = IssuedDocument::create(['uuid' => (string) Str::uuid(), 'verification_code' => Str::upper(Str::random(12)),
            'type' => $type, 'person_id' => $person->id, 'school_id' => $school->id, 'issued_at' => now(), 'file_path' => 'credentials/original.pdf',
            'payload' => ['title' => 'Documento fictício'], 'file_sha256' => hash('sha256', '%PDF-original-ficticio')]);
        Storage::disk('local')->put($document->file_path, '%PDF-original-ficticio');

        return [$school, $document];
    }

    private function user(School $school, string $role): User
    {
        $person = Person::create(['full_name' => 'Usuário Fictício '.$role, 'cpf' => str_pad((string) (Person::count() + 1), 11, '0', STR_PAD_LEFT),
            'birth_date' => '1980-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria Fictícia',
            'phone' => '66999990000', 'address' => 'Rua Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'active' => true, 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['school_id' => $role === PersonSchoolRole::ROLE_ADMINISTRATOR ? null : $school->id, 'role' => $role, 'active' => true, 'started_at' => '2020-01-01']);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
