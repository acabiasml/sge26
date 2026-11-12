<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
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
        $this->get(route('schools.edit', $school))->assertOk()->assertSee('Textos institucionais por etapa')
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

    public function test_management_and_students_cannot_change_institutional_stage_texts(): void
    {
        $school = School::create($this->schoolData());
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $this->actingAs($this->user($role, $school))->put(route('schools.update', $school),
                $this->schoolData() + ['letterhead_text_high_school' => 'Alteração indevida'])->assertForbidden();
        }
        $this->assertNull($school->fresh()->letterhead_text_high_school);
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
