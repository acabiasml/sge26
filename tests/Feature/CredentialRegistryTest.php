<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\CredentialBookArchive;
use App\Support\CredentialRegistry;
use App\Support\InstitutionalSchoolRecord;
use App\Support\SchoolRecordPdf;
use App\Support\SchoolSignatureStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\Support\SignedSchoolPdf;

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
        return app(CredentialRegistry::class)->issue($book->id, $school->id, 'technical-intermediate-certificate', $user, '2026-11-18', 'SISTEC-CERT-TESTE', function ($registration) use ($user, $school) {
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

    private function signedData(string $signed, array $signers = ['director']): array
    {
        return ['signed_pdf' => UploadedFile::fake()->createWithContent('assinado.pdf', $signed), 'validation_report' => $this->pdf('relatório'),
            'signers' => $signers, 'reviewed' => 1, 'timestamp_checked' => 1, 'validator' => 'VALIDAR — ITI'];
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
            app(CredentialRegistry::class)->issue($book->id, $school->id, 'technical-intermediate-certificate', $user, '2026-11-18', 'SISTEC-CERT-TESTE', fn () => throw new \RuntimeException('Falha de armazenamento'));
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
        $partial = SignedSchoolPdf::appendSignature($original, 'Diretora de Teste');
        $full = SignedSchoolPdf::appendSignature($partial, 'Gestora de Teste');
        $this->post(route('credential-registry.signed', $entry), $this->signedData("%PDF-1.4\nPDF sem assinatura."))->assertSessionHasErrors('signed_pdf');
        $this->post(route('credential-registry.signed', $entry), $this->signedData($partial))->assertSessionHasNoErrors();
        $this->assertFalse($entry->files()->where('kind', 'signed_document')->sole()->metadata['complete']);
        $delivery = ['method' => 'digital', 'recipient' => 'Estudante do Registro', 'recipient_kind' => 'student', 'delivered_on' => '2026-11-18',
            'reviewed' => 1, 'receipt' => $this->pdf('protocolo'), 'delivery_token' => Str::uuid()->toString()];
        $this->post(route('credential-registry.deliver', $entry), $delivery)->assertSessionHasErrors('method');
        $this->post(route('credential-registry.signed', $entry), $this->signedData($full, ['secretary']))->assertSessionHasErrors('signers');
        $this->post(route('credential-registry.signed', $entry), $this->signedData($partial, ['director', 'secretary']))->assertSessionHasErrors('signed_pdf');
        $this->post(route('credential-registry.signed', $entry), $this->signedData($full."\n% Revisão sem assinatura", ['director', 'secretary']))->assertSessionHasErrors('signed_pdf');
        $this->post(route('credential-registry.signed', $entry), $this->signedData($full, ['director', 'secretary']))->assertSessionHasNoErrors();
        $this->assertSame(2, $entry->files()->where('kind', 'signed_document')->count());
        $pending = $this->post(route('credential-registry.deliver', $entry), $delivery)->assertSessionHasNoErrors();
        $this->assertSame(1, $entry->events()->where('action', 'delivered')->count());
        $event = $entry->events()->where('action', 'delivered')->sole();
        $params = ['school' => $school->id, 'kind' => 'delivery', 'delivery_event' => $event->id];
        $pending->assertRedirect(route('institutional-records.index', $params));
        $this->post(route('credential-registry.deliver', $entry), $delivery)
            ->assertRedirect(route('institutional-records.index', $params));
        $this->assertSame(1, $entry->events()->where('action', 'delivered')->count());
        $this->get(route('institutional-records.index', ['school' => $school->id, 'kind' => 'delivery']))
            ->assertOk()->assertSee('Conferir e lançar');
        $this->get(route('institutional-records.index', $params))->assertOk()->assertSee('Os dados e comprovantes abaixo vieram');
        $deliveryBook = $this->openInstitutionalBook($school, $user, 'delivery');
        $launch = ['registry_book_id' => $deliveryBook->id, 'issuance_token' => Str::uuid()->toString(), 'delivery_event' => $event->id, 'reviewed' => 1];
        $url = route('institutional-records.store', [$school, 'delivery']);
        $this->post($url, $launch)->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
        $deliveryEntry = $deliveryBook->registrations()->sole();
        $this->post($url, array_replace($launch, ['issuance_token' => Str::uuid()->toString()]))
            ->assertRedirect(route('credential-registry.entry', $deliveryEntry));
        $this->assertSame($entry->id, $deliveryEntry->snapshot['source_registration_id']);
        $this->assertSame($entry->files()->where('kind', 'receipt')->sole()->sha256, $deliveryEntry->files()->where('kind', 'signed_receipt')->sole()->sha256);
        $this->assertTrue(app(CredentialRegistry::class)->integrity($deliveryBook->fresh()));
        $this->get(route('institutional-records.index', ['school' => $school->id, 'kind' => 'delivery']))
            ->assertOk()->assertDontSee('Conferir e lançar');
        $this->assertSame($original, Storage::disk('local')->get($doc->file_path));
        $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
        $this->get(route('documents.verify', $doc->verification_code))->assertOk()->assertSee('Registro confirmado')->assertDontSee('Estudante do Registro');
    }

    public function test_institutional_books_register_their_own_fields_and_keep_records_out_of_public_verification(): void
    {
        [$user, $school, $wrongBook] = $this->scenario();
        $other = School::create(['name' => 'Outra escola']);
        $this->get(route('institutional-records.index', ['school' => $other->id]))->assertForbidden();
        $this->post(route('institutional-records.store', [$other, 'visit']))->assertForbidden();
        foreach (InstitutionalSchoolRecord::definitions() as $kind => $definition) {
            $book = $this->openInstitutionalBook($school, $user, $kind);
            $url = route('institutional-records.store', [$school, $kind]);
            $this->get(route('institutional-records.index', ['school' => $school->id, 'kind' => $kind]))
                ->assertOk()->assertSee($definition['attachment']);
            $data = [
                'registry_book_id' => $book->id, 'issuance_token' => Str::uuid()->toString(),
                'recorded_on' => '2026-11-18', 'reviewed' => 1,
                'original_pdf' => $this->pdf('comprovante '.$kind),
            ];
            foreach ($definition['fields'] as $field => [$label, $input]) {
                $data[$field] = is_array($input) ? array_key_first($input) : 'Dado do ato '.$field;
            }
            $this->post($url, array_replace($data, ['registry_book_id' => $wrongBook->id]))->assertSessionHasErrors('registry_book_id');
            $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
            $entry = $book->registrations()->sole();
            $this->assertSame('4', $entry->referenceNumber());
            $this->assertSame('10', $entry->referenceFolio());
            $this->assertSame('Dado do ato subject', $entry->snapshot['registry_subject']);
            $this->assertNotNull($entry->files()->where('kind', 'institutional_original')->first());
            $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
            $this->get(route('credential-registry.entry', $entry))->assertOk()->assertSee('Dado do ato subject');
            $this->get(route('documents.verify', $entry->document->verification_code))->assertNotFound();
        }
        $this->assertSame(40, $wrongBook->fresh()->next_number);
    }

    public function test_external_proxy_delivery_requires_current_authority_and_preserves_both_files(): void
    {
        [$user, $school] = $this->scenario();
        $book = $this->openInstitutionalBook($school, $user, 'delivery');
        $url = route('institutional-records.store', [$school, 'delivery']);
        $data = ['registry_book_id' => $book->id, 'issuance_token' => (string) Str::uuid(),
            'recorded_on' => '2026-11-18', 'subject' => 'Certificado externo', 'recipient' => 'Procurador externo',
            'recipient_kind' => 'proxy', 'method' => 'physical', 'reviewed' => 1,
            'original_pdf' => $this->pdf('protocolo assinado'), 'authority_pdf' => $this->pdf('procuração')];
        $this->actingAs($user)->post($url, $data)->assertSessionHasErrors(['requested_on', 'authority_issued_on']);
        $this->post($url, $data + ['requested_on' => '2026-11-18', 'authority_issued_on' => '2026-05-17'])
            ->assertSessionHasErrors('authority_issued_on');
        $this->post($url, $data + ['requested_on' => '2026-11-18', 'authority_issued_on' => '2026-05-18'])
            ->assertSessionHasNoErrors();
        $entry = $book->registrations()->sole();
        $this->assertSame('2026-11-18', $entry->snapshot['requested_on']);
        $this->assertSame('2026-05-18', $entry->snapshot['authority_issued_on']);
        $this->assertEqualsCanonicalizing(['institutional_original', 'representative_authority'], $entry->files()->pluck('kind')->all());
    }

    public function test_reclassification_preserves_the_signed_summary_and_records_the_decision_and_signers(): void
    {
        [$user, $school] = $this->scenario();
        $book = $this->openInstitutionalBook($school, $user, 'special-process');
        $year = $school->academicYears()->create(['name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20']);
        $class = $year->classes()->create(['name' => 'Turma']);
        $student = $this->person('Estudante reclassificado');
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $url = route('enrollments.special-process', $enrollment);
        $this->get($url)->assertOk()->assertSee('Súmula da reclassificação');
        $data = [
            'registry_book_id' => $book->id, 'issuance_token' => Str::uuid()->toString(), 'process_kind' => 'reclassification',
            'reclassification_decision' => 'denied', 'reclassification_ground' => 'advancement',
            'signature_mode' => 'physical', 'origin' => 'Processo 3/2026', 'recorded_on' => '2026-03-01', 'reviewed' => 1,
            'evaluation_result' => 'Reposicionamento conforme processo', 'justification' => 'Comprovou competências previstas',
            'procedures' => 'Avaliação coletiva', 'original_pdf' => $this->pdf('ata individual'),
            'supporting_pdf' => $this->pdf('avaliações'),
        ];
        $this->post($url, $data)->assertSessionHasErrors(['summary_pdf', 'management_signers', 'council_signers', 'teacher_signers']);
        $this->post($url, $data + [
            'summary_pdf' => $this->pdf('súmula assinada'),
            'management_signers' => 'Gestora de Teste', 'council_signers' => 'Conselheira de Teste', 'teacher_signers' => 'Professora de Teste',
        ])->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
        $entry = $book->registrations()->sole();
        $this->assertSame('Reposicionamento conforme processo', $entry->snapshot['process_fields']['Resultado da avaliação e decisão']);
        $this->assertEqualsCanonicalizing(['supporting_document', 'signed_process_summary'], $entry->files()->pluck('kind')->all());
        $this->get(route('credential-registry.entry', $entry))->assertOk()->assertSee('Professora de Teste');
    }

    public function test_annual_and_class_final_results_can_be_registered_only_after_consolidation(): void
    {
        [$user, $school] = $this->scenario();
        $school->update(['legal_name' => 'Escola do Livro', 'inep' => '12345678', 'founded_at' => '2000-01-01',
            'phone' => '66999999999', 'email' => 'escola@example.test', 'letterhead_text' => 'Ato de teste', 'postal_code' => '78800000',
            'document_authorizations' => ['accreditation' => ['act' => 'Ato de teste', 'published_on' => '2020-01-01', 'starts_on' => '2020-01-01']]]);
        $this->person('Diretora de Teste')->schoolRoles()->create([
            'school_id' => $school->id, 'role' => 'gestor', 'position' => 'diretor', 'active' => true,
        ]);
        $book = $this->openInstitutionalBook($school, $user, 'final-results');
        $year = $school->academicYears()->create(['name' => '2025', 'reference_year' => 2025, 'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $class = $year->classes()->create(['name' => 'Turma concluída']);
        $student = $this->person('Estudante da ata');
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $url = route('academic-years.final-results.register', $year);
        $data = ['registry_book_id' => $book->id, 'issuance_token' => Str::uuid()->toString(), 'signature_mode' => 'physical'];
        $this->get(route('academic-years.final-results.prepare', $year))->assertOk()->assertSee('Ata consolidada');
        $this->get(route('academic-years.final-results.pdf', ['academicYear' => $year, 'preview' => 1]))->assertOk();
        $this->assertDatabaseCount('issued_documents', 0);
        $this->post($url, $data)->assertSessionHasErrors('document');
        $year->forceFill(['closed_at' => '2025-12-20'])->save();
        $enrollment->forceFill(['final_result_status' => StudentEnrollment::FINAL_APPROVED])->save();
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
        $this->post(route('classes.final-results.register', $class), $data)->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
        $this->assertSame(['academic-year-final-results', 'class-final-results'], $book->registrations()->orderBy('number')->get()->pluck('document.type')->all());
    }

    public function test_paper_institutional_books_require_the_physical_reference_and_preserve_it(): void
    {
        [, $school] = $this->scenario();
        $school->update(['registry_medium' => 'physical']);
        $url = route('institutional-records.store', [$school, 'inventory']);
        $data = [
            'issuance_token' => Str::uuid()->toString(), 'recorded_on' => '2026-11-05', 'reviewed' => 1,
            'subject' => 'Tombo A-123', 'movement' => 'transfer', 'asset' => 'Mesa de trabalho',
            'location' => 'Da secretaria para a biblioteca', 'custodian' => 'Pessoa responsável', 'reference' => 'Termo 11/2026',
        ];
        $this->post($url, $data)->assertSessionHasErrors(['registry_book', 'registry_folio', 'registration_date']);
        $reference = ['registry_book' => 'T-2', 'registry_folio' => '17v', 'registry_number' => '004/2026',
            'registration_date' => '2026-11-08', 'registered_by' => 'Gestora de Teste'];
        $this->post($url, $data + $reference)->assertSessionHasNoErrors()->assertRedirectContains('/livro-digital/registros/');
        $entry = IssuedDocument::where('type', 'school-inventory')->sole()->registration;
        $this->assertTrue($entry->book->isPhysical());
        $this->assertSame('004/2026', $entry->referenceNumber());
        $this->assertSame('17v', $entry->referenceFolio());
        $this->assertSame('2026-11-08', $entry->registered_on->toDateString());
        $this->assertSame('Transferência', $entry->snapshot['institutional_fields']['Movimentação']);
        $this->get(route('credential-registry.show', [$entry->book, 'q' => 'Tombo A-123']))->assertOk()->assertSee('Tombo A-123');
    }

    private function openInstitutionalBook(School $school, User $user, string $kind): \App\Models\CredentialBook
    {
        return app(CredentialRegistry::class)->open($user, [
            'school_id' => $school->id, 'kind' => $kind, 'identifier' => '002/2026', 'reference_year' => 2026,
            'first_number' => 4, 'first_folio' => 10, 'opened_on' => '2026-11-01', 'opening_term' => 'Abertura do livro.',
        ]);
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

    public function test_paper_delivery_preserves_the_signed_copy_and_checks_the_recipient_documents(): void
    {
        [$user, $school, $book] = $this->scenario();
        $entry = $this->issue($user, $school, $book)->registration;
        $paper = ['signed_pdf' => $this->pdf('via assinada em papel'), 'physical_location' => 'Acervo A, pasta 5', 'reviewed' => 1];
        $this->post(route('credential-registry.physical', $entry), $paper)->assertSessionHasErrors('physical_reason');
        $this->post(route('credential-registry.physical', $entry), $paper + ['physical_reason' => 'correction'])->assertSessionHasNoErrors();
        $copy = $entry->finalCopy();
        $this->assertSame('physical_signed', $copy->kind);
        $delivery = ['method' => 'physical', 'recipient' => 'Representante de Teste', 'signed_file_id' => $copy->id,
            'delivered_on' => '2026-11-18', 'receipt' => $this->pdf('protocolo assinado'), 'authority' => $this->pdf('comprovante'), 'reviewed' => 1];
        $this->post(route('credential-registry.deliver', $entry), $delivery + ['recipient_kind' => 'guardian'])->assertSessionHasErrors('recipient_kind');
        $proxy = $delivery + ['recipient_kind' => 'proxy', 'requested_on' => '2026-11-18'];
        $this->post(route('credential-registry.deliver', $entry), $proxy + ['authority_issued_on' => '2026-05-17'])->assertSessionHasErrors('authority_issued_on');
        $this->post(route('credential-registry.deliver', $entry), $proxy + ['authority_issued_on' => '2026-05-18'])->assertSessionHasNoErrors();
        $event = $entry->events()->where('action', 'delivered')->sole();
        $this->assertSame($copy->id, $event->data['signed_file_id']);
        $this->assertTrue(app(CredentialRegistry::class)->integrity($book->fresh()));
    }

    public function test_high_school_certificate_requires_a_signed_history_and_uses_its_modality_book(): void
    {
        [$user, $school] = $this->scenario();
        $school->update(['legal_name' => 'Escola do Livro', 'inep' => '12345678', 'founded_at' => '2000-01-01',
            'phone' => '66999999999', 'email' => 'escola@example.test', 'letterhead_text' => 'Ato autorizativo de teste', 'postal_code' => '78800000',
            'document_authorizations' => array_fill_keys(['accreditation', 'medio', 'medio_regular'], ['act' => 'Ato escolar de teste', 'published_on' => '2020-01-01', 'starts_on' => '2020-01-01'])]);
        $this->person('Diretora de Teste')->schoolRoles()->create([
            'school_id' => $school->id, 'role' => 'gestor', 'position' => 'diretor', 'active' => true,
        ]);
        $year = $school->academicYears()->create(['name' => 'Ensino Médio 2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-19', 'closed_at' => '2025-12-20']);
        $class = $year->classes()->create(['name' => '3º Ano']);
        $course = $year->courses()->create(['name' => '3º Ano do Ensino Médio', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'modality' => AcademicCourse::MODALITY_REGULAR]);
        $class->courses()->attach($course);
        $student = $this->person('Estudante do Ensino Médio');
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $enrollment->forceFill(['final_result_status' => StudentEnrollment::FINAL_APPROVED])->save();
        $enrollment->courses()->attach($course);
        $registrar = app(CredentialRegistry::class);
        $opening = ['school_id' => $school->id, 'reference_year' => 2026, 'first_number' => 1, 'first_folio' => 1,
            'opened_on' => '2026-11-01', 'opening_term' => 'Livro aberto para o teste.'];
        $historyBook = $registrar->open($user, $opening + ['kind' => 'history', 'identifier' => 'HIST-1']);
        $certificateBook = $registrar->open($user, $opening + ['kind' => 'high-school', 'identifier' => 'EM-1',
            'scope' => AcademicCourse::MODALITY_REGULAR]);
        $history = app(SchoolRecordPdf::class)->issue($user, $school, 'student-academic-history', $student->id,
            ['title' => 'Histórico do Ensino Médio', 'education_stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
                'completed_enrollment_ids' => [$enrollment->id], 'high_school_completion' => ['entered_on' => '2023-02-01',
                    'entry_year' => 2023, 'entry_method' => 'Matrícula inicial', 'completed_on' => '2025-12-19']],
            ['registry_book_id' => $historyBook->id, 'issuance_token' => (string) Str::uuid(), 'signature_mode' => 'physical'],
            fn () => "%PDF-1.4\nHistórico oficial de teste");

        $this->get(route('enrollments.high-school-certificate.prepare', $enrollment))->assertOk()->assertSee('EM-1');
        $url = route('enrollments.high-school-certificate.register', $enrollment);
        $data = ['registry_book_id' => $certificateBook->id, 'issuance_token' => (string) Str::uuid(),
            'signature_mode' => 'digital', 'history_code' => $history->verification_code];
        $authorizations = $school->document_authorizations;
        $school->update(['document_authorizations' => array_diff_key($authorizations, ['medio_regular' => true])]);
        $this->post($url, $data)->assertSessionHasErrors('document');
        $school->update(['document_authorizations' => array_replace($authorizations, ['medio_regular' => $authorizations['medio_regular'] + ['ends_on' => '2021-12-31']])]);
        $this->post($url, $data)->assertSessionHasErrors('document');
        $school->update(['document_authorizations' => $authorizations]);
        $this->post($url, $data)->assertSessionHasErrors('history_code');
        $this->post(route('credential-registry.physical', $history->registration), [
            'signed_pdf' => $this->pdf('historico assinado'), 'physical_location' => 'Acervo A, pasta 5', 'reviewed' => 1,
        ])->assertSessionHasNoErrors();
        $this->post($url, $data)->assertRedirectContains('/livro-digital/registros/');
        $entry = $certificateBook->registrations()->sole();
        $this->assertSame($history->registration->id, $entry->snapshot['history_registration_id']);
        $this->assertTrue($registrar->integrity($certificateBook->fresh()));
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
