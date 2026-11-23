<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Support\CredentialBookArchive;
use App\Support\CredentialRegistry;
use App\Support\SchoolSignatureStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CredentialRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name): Person
    {
        static $id = 0;
        $id++;

        return Person::create(['full_name' => $name, 'cpf' => str_pad((string) $id, 11, '0', STR_PAD_LEFT), 'birth_date' => '1980-01-01', 'nationality' => 'Brasileira', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'mother_name' => 'Maria de Teste', 'phone' => '66999999999', 'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'profile_completed_at' => now(), 'active' => true]);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0, 0));
        $school = School::create(['name' => 'Escola do Livro', 'cnpj' => '00176974000120', 'city' => 'Poxoréu', 'state' => 'MT', 'address' => 'Rua de Teste']);
        $person = $this->person('Gestora de Teste');
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'gestor', 'position' => 'secretário', 'active' => true, 'started_at' => '2020-01-01']);
        $user = User::factory()->create(['person_id' => $person->id]);
        $this->actingAs($user);
        $book = app(CredentialRegistry::class)->open($user, ['school_id' => $school->id, 'kind' => 'certificate', 'identifier' => '001/2026', 'reference_year' => '2026', 'first_number' => 40, 'first_folio' => 5, 'opened_on' => '2026-11-01', 'opening_term' => 'Livro de certificados, aberto conforme ato interno.', 'previous_book' => 'Livro físico, último registro 39.']);

        return [$user, $school, $book];
    }

    private function issue($user, $school, $book, ?string $token = null): IssuedDocument
    {
        return app(CredentialRegistry::class)->issue($book->id, $school->id, 'technical-intermediate-certificate', $user, '2026-11-18', null, function ($registration) use ($user, $school) {
            $document = IssuedDocument::create(['uuid' => (string) Str::uuid(), 'verification_code' => Str::upper(Str::random(12)), 'type' => 'technical-intermediate-certificate', 'person_id' => $this->person('Estudante do Registro')->id, 'school_id' => $school->id, 'issued_by_user_id' => $user->id, 'issued_at' => now(),
                'payload' => ['title' => 'Certificado Técnico Intermediário', 'student' => ['full_name' => 'Estudante do Registro'], 'program' => ['name' => 'Técnico em Móveis'], 'total_hours' => 400, 'completed_at' => '2026-10-31', 'registration' => $registration + ['director_name' => 'Diretora de Teste', 'secretary_name' => 'Gestora de Teste']]]);
            $bytes = "%PDF-1.4\noriginal-".$document->uuid;
            $path = 'originals/'.$document->uuid.'.pdf';
            Storage::disk('local')->put($path, $bytes);
            $document->update(['file_path' => $path, 'file_sha256' => hash('sha256', $bytes)]);

            return $document;
        }, $token);
    }

    private function pdf(string $content = 'arquivo'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('arquivo.pdf', "%PDF-1.4\n".$content."\n%%EOF");
    }

    private function signedData(array $signers = ['director']): array
    {
        return ['signed_pdf' => $this->pdf('assinado'), 'validation_report' => $this->pdf('relatório'), 'signers' => $signers, 'reviewed' => 1, 'timestamp_checked' => 1, 'validator' => 'VALIDAR — ITI'];
    }

    public function test_numbering_is_atomic_and_a_retried_request_returns_the_original_without_consuming_another_number(): void
    {
        [$user,$school,$book] = $this->scenario();
        $token = (string) Str::uuid();
        $one = $this->issue($user, $school, $book, $token);
        $same = $this->issue($user, $school, $book, $token);
        $two = $this->issue($user, $school, $book, (string) Str::uuid());
        $this->assertSame($one->id, $same->id);
        $this->assertSame([40, 41], $book->registrations()->orderBy('number')->pluck('number')->all());
        $this->assertSame([5, 6], $book->registrations()->orderBy('number')->pluck('folio')->all());
        $this->assertSame(42, $book->fresh()->next_number);
        $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
        $this->get(route('credential-registry.index'))->assertOk()->assertSee('001/2026');
        $this->get(route('credential-registry.show', $book))->assertOk()->assertSee('Cadeia de integridade');
        $this->get(route('credential-registry.entry', $one->registration))->assertOk()->assertSee('Aguardando assinaturas');
    }

    public function test_failed_generation_does_not_reserve_a_number_or_create_a_registration(): void
    {
        [$user,$school,$book] = $this->scenario();
        try {
            app(CredentialRegistry::class)->issue($book->id, $school->id, 'technical-intermediate-certificate', $user, '2026-11-18', null, fn () => throw new \RuntimeException('Falha de armazenamento'));
            $this->fail('Deveria falhar.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Falha de armazenamento', $error->getMessage());
        }
        $this->assertDatabaseCount('credential_registrations', 0);
        $this->assertSame(40, $book->fresh()->next_number);
    }

    public function test_a_book_from_another_school_or_of_another_type_cannot_be_used(): void
    {
        [$user,$school,$book] = $this->scenario();
        $other = School::create(['name' => 'Outra escola']);
        $this->expectException(HttpException::class);
        app(CredentialRegistry::class)->issue($book->id, $other->id, 'technical-intermediate-certificate', $user, '2026-11-18', null, fn () => null);
    }

    public function test_sistec_is_required_for_a_definitive_technical_diploma(): void
    {
        [$user,$school] = $this->scenario();
        $book = app(CredentialRegistry::class)->open($user, ['school_id' => $school->id, 'kind' => 'diploma', 'identifier' => 'D/2026', 'reference_year' => 2026, 'first_number' => 1, 'first_folio' => 1, 'opened_on' => '2026-11-01', 'opening_term' => 'Abertura.']);
        $this->expectExceptionMessage('SISTEC');
        app(CredentialRegistry::class)->issue($book->id, $school->id, 'technical-course-diploma', $user, '2026-11-18', null, fn () => null);
    }

    public function test_registered_snapshots_cannot_be_changed_or_deleted_even_by_an_administrator(): void
    {
        [$user,$school,$book] = $this->scenario();
        $doc = $this->issue($user, $school, $book);
        $entry = $doc->registration;
        $user->person->schoolRoles()->create(['role' => 'administrador', 'active' => true]);
        $this->delete(route('credential-archive.destroy', $doc))->assertSessionHasErrors('registry');
        $this->assertDatabaseHas('issued_documents', ['id' => $doc->id]);
        $this->expectException(ValidationException::class);
        $entry->update(['number' => 99]);
    }

    public function test_multiple_signatures_are_archived_in_order_and_digital_delivery_waits_for_all_signers(): void
    {
        [$user,$school,$book] = $this->scenario();
        $doc = $this->issue($user, $school, $book);
        $entry = $doc->registration;
        $original = Storage::disk('local')->get($doc->file_path);
        $this->post(route('credential-registry.signed', $entry), $this->signedData())->assertSessionHasNoErrors();
        $this->assertFalse($entry->files()->where('kind', 'signed_document')->sole()->metadata['complete']);
        $delivery = ['method' => 'digital', 'recipient' => 'Estudante do Registro', 'recipient_kind' => 'student', 'delivered_on' => '2026-11-18', 'reviewed' => 1, 'receipt' => $this->pdf('protocolo')];
        $this->post(route('credential-registry.deliver', $entry), $delivery)->assertSessionHasErrors('method');
        $this->post(route('credential-registry.signed', $entry), $this->signedData(['secretary']))->assertSessionHasErrors('signers');
        $this->post(route('credential-registry.signed', $entry), $this->signedData(['director', 'secretary']))->assertSessionHasNoErrors();
        $this->assertSame(2, $entry->files()->where('kind', 'signed_document')->count());
        $this->post(route('credential-registry.deliver', $entry), $delivery)->assertSessionHasNoErrors();
        $this->assertSame(1, $entry->events()->where('action', 'delivered')->count());
        $this->assertSame($original, Storage::disk('local')->get($doc->file_path));
        $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
        $this->get(route('documents.verify', $doc->verification_code))->assertOk()->assertSee('Registro confirmado')->assertDontSee('Estudante do Registro');
    }

    public function test_cancellation_preserves_the_number_and_original_and_keeps_public_verification_available(): void
    {
        [$user,$school,$book] = $this->scenario();
        $doc = $this->issue($user, $school, $book);
        $entry = $doc->registration;
        $this->post(route('credential-registry.cancel', $entry), ['reason' => 'Erro material confirmado na conferência.'])->assertForbidden();
        $user->person->schoolRoles()->create(['role' => 'administrador', 'active' => true]);
        $this->post(route('credential-registry.cancel', $entry), ['reason' => 'Erro material confirmado na conferência.'])->assertSessionHasNoErrors();
        $this->assertNotNull($doc->fresh()->revoked_at);
        Storage::disk('local')->assertExists($doc->file_path);
        $next = $this->issue($user, $school, $book);
        $this->assertSame(41, $next->registration->number);
        $this->get(route('documents.verify', $doc->verification_code))->assertOk()->assertSee('Documento revogado');
    }

    public function test_closed_books_cannot_accept_new_registrations_but_still_accept_signing_and_annotations(): void
    {
        [$user,$school,$book] = $this->scenario();
        $doc = $this->issue($user, $school, $book);
        $this->post(route('credential-registry.close', $book), ['closing_term' => 'Encerrado após conferência dos registros.'])->assertSessionHasNoErrors();
        $this->post(route('credential-registry.annotate', $doc->registration), ['text' => 'Conferência da referência documental.'])->assertSessionHasNoErrors();
        $this->expectExceptionMessage('encerrado');
        $this->issue($user, $school, $book);
    }

    public function test_exports_and_backups_include_numbered_folios_metadata_and_original_pdfs(): void
    {
        [$user,$school,$book] = $this->scenario();
        $doc = $this->issue($user, $school, $book);
        $archive = app(CredentialBookArchive::class);
        $pdf = $archive->pdf($book, $user);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->file_path));
        $file = $archive->backup($book, $user);
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($file->file_path));
        $this->assertSame(Storage::disk('local')->get($doc->file_path), $zip->getFromName('originais/40.pdf'));
        $metadata = json_decode($zip->getFromName('livro.json'), true);
        $this->assertSame(5, $metadata['registrations'][0]['folio']);
        $this->assertNotFalse($zip->getFromName('integridade.json'));
        $zip->close();
        $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
    }

    public function test_hash_chain_detects_database_changes_to_an_event_or_registered_data(): void
    {
        [$user,$school,$book] = $this->scenario();
        $this->issue($user, $school, $book);
        $event = $book->events()->where('action', 'registered')->sole();
        DB::table('credential_registry_events')->where('id', $event->id)->update(['data' => json_encode(['number' => 999])]);
        $this->assertFalse(app(CredentialRegistry::class)->integrity($book->fresh()));
        $this->post(route('credential-registry.export', $book))->assertStatus(409);
    }

    public function test_substitutions_cover_all_management_positions_and_expire_automatically(): void
    {
        [$user,$school] = $this->scenario();
        $replacement = $this->person('Pessoa Substituta');
        $replacement->schoolRoles()->create(['school_id' => $school->id, 'role' => 'professor', 'active' => true]);
        foreach (['diretor', 'coordenador', 'secretário'] as $position) {
            $this->post(route('management-substitutions.store'), ['school_id' => $school->id, 'person_id' => $replacement->id, 'position' => $position, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-20', 'reason' => 'Designação temporária para afastamento do titular.'])->assertSessionHasNoErrors();
        }
        $this->assertSame($replacement->id, SchoolSignatureStaff::forSchool($school, '2026-11-18')['secretário']->id);
        $this->assertSame($user->person_id, SchoolSignatureStaff::forSchool($school, '2026-11-21')['secretário']->id);
        $role = $replacement->schoolRoles()->where('role','gestor')->first();
        $this->assertTrue($role->isActiveForDate('2026-11-20'));
        $this->assertFalse($role->isActiveForDate('2026-11-21'));
        $this->get(route('management-substitutions.index'))->assertOk()->assertSee('Coordenação');
        $this->post(route('management-substitutions.store'),['school_id' => $school->id, 'person_id' => $replacement->id, 'position' => 'diretor', 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22', 'reason' => 'Outro afastamento.'])->assertSessionHasErrors('starts_on');
    }
}
