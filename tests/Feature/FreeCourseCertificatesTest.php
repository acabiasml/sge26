<?php

namespace Tests\Feature;

use App\Livewire\PeopleTable;
use App\Models\FreeCourse;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\FreeCourseCertificate;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FreeCourseCertificatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_share_the_menu_and_central_finds_the_free_course_offering(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->get(route('courses.index'))->assertOk()->assertSee('Cursos livres')->assertSee('Cursos técnicos')->assertSee($s['course']->name);
        $this->get(route('free-courses.edit', $s['course']))->assertOk()->assertSee('Conteúdo programático');
        $this->get(route('free-courses.offerings.edit', [$s['course'], $s['offering']]))->assertOk()->assertSee('Participantes e conclusões');
        $this->get(route('document-issuance.index'))->assertOk()->assertSee('Emissão de Certificado de Curso Livre');
        $this->getJson(route('document-issuance.targets', ['type' => 'free-course-certificate', 'q' => 'Marcenaria']))->assertOk()->assertJsonPath('targets.0.id', $s['offering']->id);
        $this->get(route('document-issuance.issue', ['type' => 'free-course-certificate', 'target_id' => $s['offering']->id]))->assertRedirectContains(route('free-course-certificates.prepare', $s['offering']));
        $this->get(route('free-course-certificates.prepare', $s['offering']))->assertOk()->assertSee('Selecione os concluintes');
        if (is_dir('/setup')) {
            file_put_contents('/setup/free-course-form.html', $this->get(route('free-courses.edit', $s['course']))->getContent());
            file_put_contents('/setup/free-course-offering.html', $this->get(route('free-courses.offerings.edit', [$s['course'], $s['offering']]))->getContent());
            file_put_contents('/setup/free-course-prepare.html', $this->get(route('free-course-certificates.prepare', $s['offering']))->getContent());
        }
    }

    public function test_course_registration_is_reusable_and_participation_never_creates_regular_school_enrollments(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post(route('free-courses.store'), ['school_id' => $s['school']->id, 'name' => 'Informática Livre', 'workload_hours' => 20, 'syllabus' => "Teclado\nEditor de texto", 'active' => 1])->assertSessionHasNoErrors();
        $new = FreeCourse::where('name', 'Informática Livre')->sole();
        $this->post(route('free-courses.offerings.store', $new), ['name' => 'Turma única', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-10', 'delivery_mode' => 'presencial', 'location' => 'Jarudore', 'participants' => [['person_id' => $s['people'][0]->id, 'completed' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame(1, $new->offerings()->sole()->participants()->count());
        $this->assertDatabaseCount('student_enrollments', 0);
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_preview_uses_the_two_institutional_logos_has_two_pages_and_creates_no_official_document(): void
    {
        $s = $this->scenario();
        $response = $this->actingAs($s['user'])->get(route('free-course-certificates.preview', [$s['offering'], $s['participants'][0]]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $this->assertDatabaseCount('issued_documents', 0);
        if (is_dir('/setup')) {
            file_put_contents('/setup/free-course-preview.pdf', $response->getContent());
        }
    }

    public function test_official_single_certificate_is_immutable_archived_on_the_person_and_authenticated(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]))->assertSessionHasNoErrors()->assertRedirect();
        $doc = IssuedDocument::sole();
        $original = Storage::disk('local')->get($doc->file_path);
        $this->assertSame(hash('sha256', $original), $doc->file_sha256);
        $this->assertSame($s['people'][0]->id, $doc->person_id);
        $s['course']->update(['name' => 'Título alterado depois', 'syllabus' => 'Novo conteúdo', 'workload_hours' => 99]);
        $s['people'][0]->update(['full_name' => 'Nome Alterado Depois']);
        $this->get(route('credential-archive.pdf', $doc))->assertOk()->assertContent($original);
        $this->assertSame('Marcenaria Livre', $doc->fresh()->payload['course_name']);
        $this->get(route('people.show', $s['people'][0]))->assertOk()->assertSee('Certificados e diplomas')->assertSee(route('credential-archive.pdf', $doc));
        $this->get(route('documents.verify', $doc->verification_code))->assertOk()->assertSee('Certificado de curso livre');
        $this->assertDatabaseCount('issued_documents', 1);
    }

    public function test_batch_archives_one_original_per_person_and_zip_contains_those_exact_bytes(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s))->assertSessionHasNoErrors();
        $docs = IssuedDocument::orderBy('id')->get();
        $this->assertCount(2, $docs);
        $this->assertSame([100, 101], $docs->pluck('payload.registration.registry_number')->all());
        $this->assertSame(1, $docs->pluck('batch_uuid')->unique()->count());
        $this->get(route('credential-archive.batch', $docs[0]->batch_uuid))->assertOk()->assertSee($s['people'][0]->full_name)->assertSee($s['people'][1]->full_name);
        $zipResponse = $this->get(route('credential-archive.zip', $docs[0]->batch_uuid))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $path = tempnam(sys_get_temp_dir(), 'zip-test-');
        file_put_contents($path, $zipResponse->getContent());
        $zip = new \ZipArchive;
        $zip->open($path);
        try {
            $this->assertSame(2, $zip->numFiles);
            foreach ($docs as $i => $doc) {
                $this->assertSame(Storage::disk('local')->get($doc->file_path), $zip->getFromIndex($i));
            }
        } finally {
            $zip->close();
            unlink($path);
        }
        $this->assertDatabaseCount('issued_documents', 2);
    }

    public function test_incomplete_future_removed_or_foreign_participants_cannot_be_officially_certified(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user']);
        $s['participants'][0]->update(['completed_at' => null]);
        $this->post(route('free-course-certificates.store', $s['offering']), $this->fields($s))->assertSessionHasErrors('participant_ids');
        $s['participants'][0]->update(['completed_at' => '2026-10-10', 'removed_at' => now()]);
        $this->post(route('free-course-certificates.store', $s['offering']), $this->fields($s))->assertSessionHasErrors('participant_ids');
        $s['participants'][0]->update(['removed_at' => null]);
        $s['offering']->update(['ends_at' => '2027-01-01']);
        $this->post(route('free-course-certificates.store', $s['offering']->fresh()), $this->fields($s))->assertSessionHasErrors('participant_ids');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_changed_course_data_and_incomplete_registry_are_rejected_before_any_issuance(): void
    {
        $s = $this->scenario();
        $data = $this->fields($s);
        $s['course']->update(['syllabus' => 'Ementa revisada']);
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $data)->assertSessionHasErrors('document');
        $data = $this->fields($s);
        unset($data['registry_book']);
        $this->post(route('free-course-certificates.store', $s['offering']), $data)->assertSessionHasErrors('registry_book');
        $data = $this->fields($s);
        $data['director_name'] = 'Eleonora M.';
        $this->post(route('free-course-certificates.store', $s['offering']), $data)->assertSessionHasErrors('director_name');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_conflicting_registration_rolls_back_the_whole_batch_and_removes_new_files(): void
    {
        $s = $this->scenario();
        IssuedDocument::create(['uuid' => (string) Str::uuid(), 'verification_code' => 'REGISTROANTIGO', 'type' => 'free-course-certificate', 'school_id' => $s['school']->id, 'person_id' => $s['people'][1]->id, 'issued_at' => now(), 'payload' => [],
            'registration_key' => hash('sha256', implode('|', [$s['school']->id, 'free-course-certificate', '01', '101']))]);
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s))->assertSessionHasErrors('registry_number');
        $this->assertDatabaseCount('issued_documents', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('credentials'));
    }

    public function test_other_school_cannot_view_prepare_issue_or_download_the_certificate_or_batch(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]))->assertSessionHasNoErrors();
        $doc = IssuedDocument::sole();
        $other = School::create(['name' => 'Outra escola']);
        $user = $this->manager($other);
        $this->actingAs($user)->get(route('free-courses.edit', $s['course']))->assertForbidden();
        $this->get(route('free-course-certificates.prepare', $s['offering']))->assertForbidden();
        $this->post(route('free-course-certificates.store', $s['offering']), $this->fields($s))->assertForbidden();
        $this->get(route('credential-archive.pdf', $doc))->assertForbidden();
        $this->get(route('credential-archive.batch', $doc->batch_uuid))->assertForbidden();
        $this->get(route('credential-archive.zip', $doc->batch_uuid))->assertForbidden();
        $this->getJson(route('document-issuance.targets', ['type' => 'free-course-certificate', 'q' => 'Marcenaria']))->assertOk()->assertJsonCount(0, 'targets');
        $this->get(route('courses.index', ['school_id' => $s['school']->id]))->assertForbidden();
    }

    public function test_missing_or_modified_archived_file_is_never_rebuilt_using_current_data(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]))->assertSessionHasNoErrors();
        $doc = IssuedDocument::sole();
        Storage::disk('local')->put($doc->file_path, 'Arquivo modificado');
        $this->get(route('credential-archive.pdf', $doc))->assertStatus(409);
        Storage::disk('local')->delete($doc->file_path);
        $this->get(route('credential-archive.pdf', $doc))->assertNotFound();
        $this->assertDatabaseCount('issued_documents', 1);
    }

    public function test_long_program_content_paginates_without_dropping_the_end(): void
    {
        $s = $this->scenario();
        $text = implode("\n", array_fill(0, 45, 'Operação segura de ferramentas e técnicas de acabamento.'))."\nÚltimo conteúdo preservado.";
        $s['course']->update(['syllabus' => $text]);
        $pages = FreeCourseCertificate::syllabusPages($text);
        $this->assertCount(3, $pages);
        $this->assertSame('Último conteúdo preservado.', end($pages)[9]);
        $response = $this->actingAs($s['user'])->get(route('free-course-certificates.preview', [$s['offering'], $s['participants'][0]]))->assertOk();
        $this->assertSame(4, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
    }

    public function test_school_class_import_respects_enrollment_dates_and_does_not_mark_completion(): void
    {
        $s = $this->scenario();
        $year = $s['school']->academicYears()->create(['name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20']);
        $class = $year->classes()->create(['name' => '2º Ano', 'active' => true]);
        $class->enrollments()->create(['person_id' => $s['people'][0]->id, 'enrolled_at' => '2026-02-01', 'status' => 'transferido', 'transferred_at' => '2026-09-01']);
        $class->enrollments()->create(['person_id' => $s['people'][1]->id, 'enrolled_at' => '2026-02-01', 'status' => 'matriculado']);
        $url = route('free-courses.class-participants', [$s['course'], $class]).'?starts_at=2026-10-01&ends_at=2026-10-10';
        $this->actingAs($s['user'])->getJson($url)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $s['people'][1]->id);
        $this->get(route('free-courses.offerings.edit', [$s['course'], $s['offering']]))->assertOk()->assertSee('Adicionar estudantes de uma turma escolar');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_failure_to_save_the_original_pdf_rolls_back_the_official_issuance(): void
    {
        $s = $this->scenario();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($s['user'])->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]));
            $this->fail('A emissão exige o arquivo original.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Não foi possível arquivar', $error->getMessage());
        }
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_certificate_requires_and_snapshots_cpf_birthplace_nationality_and_birth_date(): void
    {
        $s = $this->scenario();
        $s['people'][0]->update(['cpf' => null]);
        $this->actingAs($s['user'])->get(route('free-course-certificates.prepare', $s['offering']))->assertOk()->assertSee('Falta preencher: CPF');
        $this->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('issued_documents', 0);
        $s['people'][0]->update(['cpf' => '12345678901']);
        $this->post(route('free-course-certificates.store', $s['offering']), $this->fields($s, [$s['participants'][0]->id]))->assertSessionHasNoErrors();
        $identity = IssuedDocument::sole()->payload['participant_identity'];
        $this->assertSame('12345678901', preg_replace('/\D/', '', $identity['cpf']));
        $this->assertSame('2005-03-10', $identity['birth_date']);
        $this->assertSame('Poxoréu', $identity['birth_city']);
        $this->assertSame('MT', $identity['birth_state']);
        $this->assertSame('Brasileira', $identity['nationality']);
    }

    public function test_course_participant_remains_searchable_and_archive_accessible_after_other_roles_end(): void
    {
        $s = $this->scenario();
        $person = $s['people'][0];
        $person->schoolRoles()->delete();
        $this->actingAs($s['user'])->get(route('people.show', $person))->assertOk()->assertSee('Certificados e diplomas');
        Livewire::test(PeopleTable::class)->set('search', $person->full_name)->assertSee($person->full_name);
        $outside = School::create(['name' => 'Sem acesso']);
        $viewer = $this->manager($outside);
        $this->actingAs($viewer)->get(route('people.show', $person))->assertForbidden();
    }

    private function fields(array $s, ?array $ids = null): array
    {
        return ['participant_ids' => $ids ?? array_map(fn ($p) => $p->id, $s['participants']), 'expected_version' => app(FreeCourseCertificate::class)->version($s['offering']->fresh()),
            'registry_book' => '01', 'registry_folio' => '20', 'registry_number' => 100, 'registered_by' => 'Marina Alves Fictícia', 'issued_date' => '2026-11-17', 'issued_place' => 'Poxoréu-MT', 'director_name' => 'Eleonora Silva Fictícia', 'instructor_name' => 'Instrutor José Fictício', 'instructor_role' => 'Instrutor'];
    }

    private function manager(School $school): User
    {
        $p = Person::create(['full_name' => 'Gestor Fictício '.$school->id, 'cpf' => str_pad((string) $school->id, 11, '0', STR_PAD_LEFT), 'birth_date' => '1980-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria Fictícia', 'phone' => '66999990000', 'address' => 'Rua Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'active' => true, 'profile_completed_at' => now()]);
        $p->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'active' => true, 'started_at' => '2020-01-01']);

        return User::factory()->create(['person_id' => $p->id]);
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
        Storage::fake('local');
        $school = School::create(['name' => 'Escola Fictícia de Formação', 'cnpj' => '00.176.974/0001-20', 'address' => 'Rua da Formação', 'number' => '10', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'logo_path' => 'brand/logo.png', 'active' => true]);
        $user = $this->manager($school);
        $course = FreeCourse::create(['school_id' => $school->id, 'name' => 'Marcenaria Livre', 'workload_hours' => 40, 'syllabus' => "Segurança na oficina\nFerramentas manuais\nTécnicas de acabamento", 'active' => true]);
        $offering = $course->offerings()->create(['name' => 'Turma de outubro', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-10', 'location' => 'Jarudore', 'delivery_mode' => 'presencial']);
        $people = [];
        $participants = [];
        foreach (['Participante Um Fictício', 'Participante Dois Fictício'] as $name) {
            $person = Person::create(['full_name' => $name, 'active' => true, 'cpf' => count($people) ? '98765432100' : '12345678901', 'birth_date' => '2005-03-10', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira']);
            $person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_STUDENT, 'active' => true]);
            $people[] = $person;
            $participants[] = $offering->participants()->create(['person_id' => $person->id, 'completed_at' => '2026-10-10']);
        }

        return compact('school', 'user', 'course', 'offering', 'people', 'participants');
    }
}
