<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\HistoryDocumentVersion;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolLetterheadStageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('stageCases')]
    public function test_letterhead_uses_only_the_relevant_stage_with_shared_fallback(string|array|null $stages, array $expected): void
    {
        $school = new School(['name' => 'Escola de teste', 'letterhead_text' => 'Autorização padrão',
            'letterhead_text_elementary' => 'Autorização do Fundamental', 'letterhead_text_high_school' => 'Autorização do Médio']);
        $lines = PdfLetterhead::make($school, $stages)['lines'];
        $this->assertSame($expected, array_slice($lines, 3));

        $school->letterhead_text_elementary = null;
        $this->assertSame(['Autorização padrão'], $school->letterheadTextsForStages(['fundamental']));
        $school->letterhead_text_high_school = 'Autorização padrão';
        $this->assertSame(['Autorização padrão'], $school->letterheadTextsForStages(['fundamental', 'medio']));
    }

    public static function stageCases(): array
    {
        return [
            'general' => [null, ['Autorização padrão']],
            'elementary' => ['fundamental', ['Autorização do Fundamental']],
            'high-school' => ['medio', ['Autorização do Médio']],
            'technical' => ['tecnico', ['Autorização padrão']],
            'parallel-technical' => [['medio', 'tecnico'], ['Autorização do Médio']],
            'repeated-stage' => [['medio', 'medio'], ['Autorização do Médio']],
            'mixed' => [['medio', 'fundamental'], ['Ensino Fundamental: Autorização do Fundamental', 'Ensino Médio: Autorização do Médio']],
        ];
    }

    public function test_administrator_can_save_optional_stage_texts_and_clear_them_without_changing_the_default(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $data = $this->schoolData() + ['letterhead_text_elementary' => 'Portaria EF 123/2026', 'letterhead_text_high_school' => 'Resolução EM 456/2026'];
        $this->post(route('schools.store'), $data)->assertSessionHasNoErrors();
        $school = School::where('cnpj', $data['cnpj'])->sole();
        $this->assertSame('Portaria EF 123/2026', $school->letterhead_text_elementary);
        $this->assertSame('Resolução EM 456/2026', $school->letterhead_text_high_school);
        $this->get(route('schools.edit', $school))->assertOk()->assertSee('Linha institucional do cabeçalho')
            ->assertSee('Portaria EF 123/2026')->assertSee('Resolução EM 456/2026');

        $this->put(route('schools.update', $school), $this->schoolData())->assertSessionHasNoErrors();
        $this->assertSame('Portaria EF 123/2026', $school->fresh()->letterhead_text_elementary);
        $this->put(route('schools.update', $school), array_replace($data, ['letterhead_text_high_school' => str_repeat('x', 5001)]))
            ->assertSessionHasErrors('letterhead_text_high_school');
        $this->assertSame('Resolução EM 456/2026', $school->fresh()->letterhead_text_high_school);

        $this->put(route('schools.update', $school), array_replace($data, ['letterhead_text_elementary' => '', 'letterhead_text_high_school' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($school->fresh()->letterhead_text_elementary);
        $this->assertNull($school->fresh()->letterhead_text_high_school);
        $this->assertSame($data['letterhead_text'], $school->fresh()->letterhead_text);
        $this->assertTrue($school->fresh()->hasRequiredLetterheadForOfficialDocuments());
    }

    public function test_only_management_can_change_institutional_stage_texts_in_its_school(): void
    {
        $school = School::create($this->schoolData());
        foreach ([PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $this->actingAs($this->user($role, $school))->put(route('schools.update', $school),
                $this->schoolData() + ['letterhead_text_high_school' => 'Alteração indevida'])->assertForbidden();
        }
        $this->assertNull($school->fresh()->letterhead_text_high_school);

        $this->actingAs($this->user(PersonSchoolRole::ROLE_MANAGER, $school))->put(route('schools.update', $school),
            $this->schoolData() + ['letterhead_text_high_school' => 'Autorização da escola'])->assertSessionHasNoErrors();
        $this->assertSame('Autorização da escola', $school->fresh()->letterhead_text_high_school);
    }

    public function test_simplified_letterhead_switches_between_one_and_two_texts(): void
    {
        $school = School::create($this->schoolData() + ['letterhead_text_high_school' => 'Médio antigo']);
        $this->actingAs($this->user(PersonSchoolRole::ROLE_MANAGER, $school));
        $url = route('schools.update', $school);
        $form = array_replace($this->schoolData(), ['letterhead_mode' => 'by_stage', 'letterhead_text' => 'Texto livre do Fundamental']);

        $this->get(route('schools.edit', $school))->assertOk()->assertSee('value="by_stage" selected', false)
            ->assertSee('Médio antigo')->assertDontSee('name="letterhead_text_elementary"', false);
        $this->put($url, $form)->assertSessionHasErrors('letterhead_text_high_school');
        $this->put($url, $form + ['letterhead_text_high_school' => 'Texto livre do Médio'])->assertSessionHasNoErrors();
        $school->refresh();
        $this->assertSame(['Texto livre do Fundamental'], $school->letterheadTextsForStages(['fundamental']));
        $this->assertSame(['Texto livre do Médio'], $school->letterheadTextsForStages(['medio']));
        $this->assertSame(['Autorização padrão'], $school->letterheadTextsForStages());

        $this->put($url, array_replace($form, ['letterhead_mode' => 'shared', 'letterhead_text' => 'Mantenha minha redação livre.']))
            ->assertSessionHasNoErrors();
        $school->refresh();
        $this->assertNull($school->letterhead_text_elementary);
        $this->assertNull($school->letterhead_text_high_school);
        $this->assertSame(['Mantenha minha redação livre.'], $school->letterheadTextsForStages(['fundamental', 'medio']));
        $this->get(route('schools.edit', $school))->assertOk()->assertSee('value="shared" selected', false);
    }

    public function test_authorizations_are_saved_separately_from_the_letterhead(): void
    {
        $school = School::create($this->schoolData());
        $otherSchool = School::create(array_replace($this->schoolData(), ['cnpj' => '11.222.333/0001-81', 'inep' => '51000001']));
        $this->actingAs($this->user(PersonSchoolRole::ROLE_MANAGER, $school));
        $edit = route('schools.edit', $school);
        $tab = $edit.'#section-atos-autorizativos';
        $this->get($edit)->assertOk()->assertSee($tab)->assertSee('name="document_authorizations[medio_educacao_do_campo][act]"', false);
        $this->get(route('schools.document-authorizations.edit', $school))->assertRedirect($tab);
        $this->get(route('schools.document-authorizations.edit', $otherSchool))->assertForbidden();

        $act = ['act' => 'Ato de teste', 'published_on' => '2020-01-01', 'starts_on' => '2020-01-01',
            'source_url' => 'https://iomat.mt.gov.br/portal/edicoes/download/17136'];
        $this->put(route('schools.update', $school), array_replace($this->schoolData(), [
            'letterhead_mode' => 'shared', 'letterhead_text' => 'Nova linha',
            'document_authorizations' => ['medio_educacao_do_campo' => $act],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($act, $school->fresh()->document_authorizations['medio_educacao_do_campo']);
        $this->assertSame(['Nova linha'], $school->fresh()->letterheadTextsForStages());
        $this->get($edit)->assertOk()->assertSee('Abrir publicação')->assertSee($act['source_url']);
    }

    public function test_published_acts_fill_empty_offerings_by_cnpj_and_preserve_existing_data(): void
    {
        $existing = ['act' => 'Credenciamento já registrado', 'published_on' => '2021-01-01', 'starts_on' => '2021-01-01'];
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-24 12:00', 'America/Cuiaba'));
        $lar = School::create(array_replace($this->schoolData(), ['cnpj' => '00.176.974/0003-92',
            'letterhead_text' => 'Credenciada pela Portaria Nº 042/2005 CEE/MT e autorizada pelo Ato nº 674/2019 CEE/MT publicado em 12/12/2019.']));
        $laura = School::create(array_replace($this->schoolData(), ['cnpj' => '00176974000201', 'inep' => '51000001']));
        $liceu = School::create(array_replace($this->schoolData(), ['cnpj' => '00.176.974/0001-20', 'inep' => '51000002']));
        $other = School::create(array_replace($this->schoolData(), ['cnpj' => '11.222.333/0001-81', 'inep' => '51000003']));
        $program = $liceu->technicalPrograms()->create([
            'name' => 'Curso Técnico em Móveis', 'graduate_title' => 'Técnico em Móveis', 'modules' => [],
        ]);
        $migration = require database_path('migrations/2026_11_24_000003_pre_fill_published_school_authorizations.php');
        $migration->up();
        $acts = $lar->fresh()->document_authorizations;
        $this->assertStringContainsString('Res. 384/04, art. 44', $acts['accreditation']['act']);
        $this->assertNull($acts['accreditation']['ends_on']);
        $lar->update(['document_authorizations' => array_replace($acts, ['accreditation' => $existing])]);
        $migration->up();

        $this->assertSame($existing, $lar->fresh()->document_authorizations['accreditation']);
        $this->assertSame('2027-12-31', $acts['fundamental_educacao_do_campo']['ends_on']);
        $this->assertStringContainsString('078/2023', $acts['medio_educacao_do_campo']['act']);
        $this->assertArrayNotHasKey('medio_regular', $acts);
        $this->assertStringContainsString('383/2025', $lar->fresh()->letterhead_text_elementary);
        $this->assertStringContainsString('078/2023', $lar->fresh()->letterhead_text_high_school);
        $oldElementary = OfficialDocumentCompliance::authorizationDetails($lar->fresh(), ['fundamental'], [['fundamental', 'Regular', 2024]]);
        $this->assertNull($oldElementary['message']);
        $this->assertSame('2025-01-01', $oldElementary['acts']['fundamental_educacao_do_campo']['starts_on']);
        $this->assertNotNull(OfficialDocumentCompliance::authorizationDetails($lar->fresh(), ['fundamental'], [['fundamental', 'Regular', 2026]])['message']);
        $this->assertSame('2029-12-31', $laura->fresh()->document_authorizations['medio_regular']['ends_on']);
        $this->assertSame('2025-12-31', $liceu->fresh()->document_authorizations['fundamental_regular']['ends_on']);
        $this->assertStringContainsString('261/2008-CEE/MT', $laura->fresh()->document_authorizations['accreditation']['act']);
        $this->assertNull($laura->fresh()->document_authorizations['accreditation']['ends_on']);
        $this->assertStringContainsString('294/2008-CEE/MT', $liceu->fresh()->document_authorizations['accreditation']['act']);
        $this->assertNull($liceu->fresh()->document_authorizations['accreditation']['ends_on']);
        $liceu->refresh();
        $renewal = OfficialDocumentCompliance::authorizationDetails($liceu, ['fundamental', 'medio'],
            [['fundamental', 'Regular', 2026], ['medio', 'Regular', 2026]]);
        $this->assertNull($renewal['message']);
        $this->assertSame(['accreditation'], array_keys($renewal['acts']));
        $this->assertCount(1, $renewal['renewal_notices']);
        $this->assertStringContainsString('SIPE nº 1757/2025, protocolado em 23/10/2025', $renewal['renewal_notices'][0]);
        $this->assertStringContainsString('Vigência encerrada em 31/12/2025', $renewal['renewal_notices'][0]);

        $this->assertSame(['concomitante', 'subsequente'], $program->fresh()->offer_modes);
        $technical = new AcademicCourse(['stage' => 'tecnico', 'modality' => AcademicCourse::MODALITY_PROFESSIONAL_TECHNOLOGICAL,
            'technical_program_id' => $program->id]);
        $technical->setRelation('technicalProgram', $program->fresh());
        $technicalAuthorization = OfficialDocumentCompliance::courseAuthorizationDetails($liceu, [$technical], 2026);
        $this->assertNull($technicalAuthorization['message']);
        $this->assertSame([], $technicalAuthorization['renewal_notices']);
        $this->assertNull($other->fresh()->document_authorizations);
    }

    public function test_history_preview_version_follows_the_line_for_its_stage(): void
    {
        $school = new School($this->schoolData() + [
            'letterhead_text_elementary' => 'Texto do Fundamental',
            'letterhead_text_high_school' => 'Texto do Médio',
        ]);
        $record = new StudentAcademicHistory(['education_stage' => 'fundamental']);
        $record->setRelation('student', new Person(['full_name' => 'Estudante']));
        $record->setRelation('school', $school);
        $record->setRelation('years', collect());
        $record->setRelation('components', collect());

        $version = HistoryDocumentVersion::of($record);
        $school->letterhead_text_high_school = 'Texto atualizado do Médio';
        $this->assertSame($version, HistoryDocumentVersion::of($record));
        $school->letterhead_text_elementary = 'Texto atualizado do Fundamental';
        $this->assertNotSame($version, HistoryDocumentVersion::of($record));
    }

    public function test_student_documents_and_class_letterhead_respect_enrollment_stage_and_parallel_technical_courses(): void
    {
        $school = School::create($this->schoolData() + ['letterhead_text_elementary' => 'Autorização EF exclusiva',
            'letterhead_text_high_school' => 'Autorização EM exclusiva']);
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Educação Básica', 'reference_year' => 2026,
            'starts_at' => '2026-02-01', 'ends_at' => '2026-12-18', 'approved_at' => '2026-01-01', 'active' => true]);
        $year->periods()->create(['name' => '1º Bimestre', 'starts_at' => '2026-02-01', 'ends_at' => '2026-04-10', 'position' => 1]);
        $technical = $year->courses()->create(['name' => 'Itinerário técnico', 'stage' => AcademicCourse::STAGE_TECHNICAL, 'active' => true]);
        $rendered = [];
        view()->composer(['reports.student-report-card', 'reports.student-individual-record', 'reports.certificates.transfer'], function ($view) use (&$rendered): void {
            $rendered[] = $view->getData()['letterhead']['lines'];
        });
        foreach (['fundamental' => 'EF', 'medio' => 'EM'] as $stage => $label) {
            $course = $year->courses()->create(['name' => 'Matriz '.$stage, 'stage' => $stage, 'active' => true]);
            $course->components()->create(['name' => 'Matemática', 'weekly_lessons' => 5, 'workload_hours' => 160, 'active' => true]);
            $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'Turma '.$stage, 'active' => true]);
            $class->courses()->attach($course);
            $enrollment = $class->enrollments()->create(['person_id' => $admin->person_id, 'enrolled_at' => '2026-02-01',
                'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
            $enrollment->courses()->attach($course);
            if ($stage === 'medio') {
                $enrollment->courses()->attach($technical);
                $class->courses()->attach($technical);
            }
            $this->assertContains('Autorização '.$label.' exclusiva', PdfLetterhead::forClass($class)['lines']);
            foreach (['enrollments.report-card.pdf', 'enrollments.individual-record.pdf', 'enrollments.transfer-certificate.pdf'] as $route) {
                $this->get(route($route, $enrollment))->assertOk()->assertHeader('content-type', 'application/pdf');
                $lines = array_pop($rendered);
                $this->assertContains('Autorização '.$label.' exclusiva', $lines);
                $this->assertNotContains('Autorização '.($label === 'EF' ? 'EM' : 'EF').' exclusiva', $lines);
                $this->assertNotContains($school->letterhead_text, $lines);
            }
            $enrollment->courses()->detach();
            $this->assertContains('Autorização '.$label.' exclusiva', PdfLetterhead::forEnrollment($enrollment->fresh())['lines']);
            $enrollment->courses()->attach($course);
            $history = app(UnifiedStudentHistorySynchronizer::class)->synchronize($admin->person, $school->id, $admin->person_id, $stage);
            $this->assertSame('Autorização '.$label.' exclusiva', $history->years()->where('student_enrollment_id', $enrollment->id)->sole()->school_authorization);
        }
    }

    private function schoolData(): array
    {
        return ['name' => 'Escola de teste', 'legal_name' => 'Instituição de teste', 'cnpj' => '00.176.974/0001-20', 'inep' => '51000000',
            'founded_at' => '1990-01-01', 'phone' => '(66) 3000-0000', 'email' => 'escola@example.org', 'website' => 'https://example.org',
            'letterhead_text' => 'Autorização padrão', 'address' => 'Rua da Escola', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78800-000', 'active' => true];
    }

    private function user(string $role, ?School $school = null): User
    {
        $person = Person::create(['full_name' => 'Pessoa '.$role, 'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $school?->id, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
