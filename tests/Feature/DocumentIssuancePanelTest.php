<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentIssuancePanelTest extends TestCase
{
    use RefreshDatabase;

    private int $personSequence = 0;

    public function test_administration_and_management_can_open_the_document_issuance_panel(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id);

        $this->actingAs($administrator)
            ->get(route('document-issuance.index'))
            ->assertOk()
            ->assertSee('Central de emissão')
            ->assertSee('value="enrollment-declaration"', false)
            ->assertSee('value="academic-calendar"', false)
            ->assertSee('value="class-report-cards"', false)
            ->assertSee('value="class-grade-mirror"', false)
            ->assertSee('name="attendance_scope"', false)
            ->assertSee('Ficha cadastral da escola');

        $this->actingAs($manager)
            ->get(route('document-issuance.index'))
            ->assertOk()
            ->assertSee('Central de emissão')
            ->assertDontSee('Ficha cadastral da escola');
    }

    public function test_regular_users_cannot_open_the_document_issuance_panel(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $teacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $school->id);

        $this->actingAs($teacher)
            ->get(route('document-issuance.index'))
            ->assertForbidden();
    }

    public function test_manager_search_only_returns_people_from_managed_school(): void
    {
        $managedSchool = School::query()->create(['name' => 'Escola Gerenciada', 'active' => true]);
        $otherSchool = School::query()->create(['name' => 'Outra Escola', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $managedSchool->id);
        $visiblePerson = $this->personWithRole('Pessoa Visível', PersonSchoolRole::ROLE_STUDENT, $managedSchool->id);
        $this->personWithRole('Pessoa de Outra Escola', PersonSchoolRole::ROLE_STUDENT, $otherSchool->id);

        $this->actingAs($manager)
            ->getJson(route('document-issuance.targets', [
                'type' => 'person-record',
                'q' => 'Pessoa',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $visiblePerson->id, 'title' => 'Pessoa Visível'])
            ->assertJsonMissing(['title' => 'Pessoa de Outra Escola']);

        $this->actingAs($manager)
            ->getJson(route('document-issuance.targets', [
                'type' => 'person-record',
                'school_id' => $otherSchool->id,
            ]))
            ->assertForbidden();
    }

    public function test_report_card_selection_redirects_to_existing_pdf_emitter_with_score_view(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante Teste', PersonSchoolRole::ROLE_STUDENT, $school->id);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => '1º Ano A',
            'active' => true,
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($administrator)
            ->get(route('document-issuance.issue', [
                'type' => 'report-card',
                'target_id' => $enrollment->id,
                'score_view' => 'conceitos',
            ]))
            ->assertRedirect(route('enrollments.report-card.pdf', [
                'enrollment' => $enrollment,
                'notas' => 'conceitos',
            ]));
    }

    public function test_enrollment_search_returns_the_most_recent_enrollment_first(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante com Histórico', PersonSchoolRole::ROLE_STUDENT, $school->id);

        $olderYear = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2025,
            'starts_at' => '2025-01-01',
            'ends_at' => '2025-12-31',
            'active' => false,
        ]);
        $currentYear = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $olderClass = SchoolClass::query()->create([
            'academic_year_id' => $olderYear->id,
            'name' => '1º Ano',
            'active' => false,
        ]);
        $currentClass = SchoolClass::query()->create([
            'academic_year_id' => $currentYear->id,
            'name' => '2º Ano',
            'active' => true,
        ]);
        $olderEnrollment = StudentEnrollment::query()->create([
            'school_class_id' => $olderClass->id,
            'person_id' => $student->id,
            'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $currentEnrollment = StudentEnrollment::query()->create([
            'school_class_id' => $currentClass->id,
            'person_id' => $student->id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $response = $this->actingAs($administrator)
            ->getJson(route('document-issuance.targets', [
                'type' => 'enrollment-declaration',
                'q' => 'Estudante com Histórico',
            ]))
            ->assertOk();

        $response->assertJsonPath('targets.0.id', $currentEnrollment->id);
        $response->assertJsonPath('targets.0.enabled', true);
        $response->assertJsonPath('targets.0.reason', null);
        $this->assertCount(1, $response->json('targets'));

        $archiveResponse = $this->actingAs($administrator)
            ->getJson(route('document-issuance.targets', [
                'type' => 'individual-record',
                'q' => 'Estudante com',
            ]))
            ->assertOk();

        $archiveResponse->assertJsonPath('targets.0.id', $currentEnrollment->id)
            ->assertJsonPath('targets.1.id', $olderEnrollment->id);
    }

    public function test_current_enrollment_documents_keep_ongoing_technical_enrollments_visible(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante Tecnico', PersonSchoolRole::ROLE_STUDENT, $school->id);

        $closedYear = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educacao Basica',
            'reference_year' => 2025,
            'starts_at' => '2025-01-01',
            'ends_at' => '2025-12-31',
            'active' => false,
            'closed_at' => '2025-12-31 18:00:00',
        ]);
        $technicalClass = SchoolClass::query()->create([
            'academic_year_id' => $closedYear->id,
            'name' => 'Curso Tecnico em Moveis',
            'active' => true,
        ]);
        $regularClass = SchoolClass::query()->create([
            'academic_year_id' => $closedYear->id,
            'name' => '2 Ano',
            'active' => false,
        ]);
        $technicalCourse = AcademicCourse::query()->create([
            'academic_year_id' => $closedYear->id,
            'name' => 'Curso Tecnico em Moveis',
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'modality' => AcademicCourse::MODALITY_PROFESSIONAL_TECHNOLOGICAL,
            'active' => true,
        ]);
        $regularCourse = AcademicCourse::query()->create([
            'academic_year_id' => $closedYear->id,
            'name' => '2 Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'modality' => AcademicCourse::MODALITY_REGULAR,
            'active' => false,
        ]);
        $technicalCourse->classes()->attach($technicalClass);
        $regularCourse->classes()->attach($regularClass);
        $technicalEnrollment = StudentEnrollment::query()->create([
            'school_class_id' => $technicalClass->id,
            'person_id' => $student->id,
            'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $regularEnrollment = StudentEnrollment::query()->create([
            'school_class_id' => $regularClass->id,
            'person_id' => $student->id,
            'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $technicalEnrollment->courses()->attach($technicalCourse);
        $regularEnrollment->courses()->attach($regularCourse);

        $response = $this->actingAs($administrator)
            ->getJson(route('document-issuance.targets', [
                'type' => 'enrollment-form',
                'q' => 'Estudante Tecnico',
            ]))
            ->assertOk();

        $response->assertJsonPath('targets.0.id', $technicalEnrollment->id);
        $this->assertCount(1, $response->json('targets'));
    }

    public function test_current_enrollment_documents_cannot_be_forced_for_closed_regular_year(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante Arquivado', PersonSchoolRole::ROLE_STUDENT, $school->id);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educacao Basica',
            'reference_year' => 2025,
            'starts_at' => '2025-01-01',
            'ends_at' => '2025-12-31',
            'active' => false,
            'closed_at' => '2025-12-31 18:00:00',
        ]);
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => '1 Ano',
            'active' => false,
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($administrator)
            ->from(route('document-issuance.index'))
            ->get(route('document-issuance.issue', [
                'type' => 'enrollment-form',
                'target_id' => $enrollment->id,
            ]))
            ->assertRedirect(route('document-issuance.index'))
            ->assertSessionHasErrors('target_id');
    }

    public function test_class_academic_documents_redirect_to_their_emitters_with_score_view(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante da Turma', PersonSchoolRole::ROLE_STUDENT, $school->id);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => '2º Ano',
            'active' => true,
        ]);
        StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($administrator)
            ->get(route('document-issuance.issue', [
                'type' => 'class-report-cards',
                'target_id' => $class->id,
                'score_view' => 'numeros',
            ]))
            ->assertRedirect(route('classes.report-cards.pdf', [
                'class' => $class,
                'notas' => 'numeros',
            ]));

        $this->actingAs($administrator)
            ->get(route('document-issuance.issue', [
                'type' => 'class-grade-mirror',
                'target_id' => $class->id,
                'score_view' => 'conceitos',
            ]))
            ->assertRedirect(route('classes.grade-mirror.pdf', [
                'class' => $class,
                'notas' => 'conceitos',
            ]));
    }

    public function test_attendance_certificate_selection_preserves_the_period_scope(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $student = $this->personWithRole('Estudante Frequência', PersonSchoolRole::ROLE_STUDENT, $school->id);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-10',
            'position' => 1,
        ]);
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => '1º Ano',
            'active' => true,
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($administrator)
            ->get(route('document-issuance.issue', [
                'type' => 'attendance-certificate',
                'target_id' => $enrollment->id,
                'attendance_scope' => 'period',
                'academic_period_id' => $period->id,
            ]))
            ->assertRedirect(route('enrollments.attendance-certificate.pdf', [
                'enrollment' => $enrollment,
                'attendance_scope' => 'period',
                'academic_period_id' => $period->id,
            ]));
    }

    public function test_manager_cannot_force_emission_for_enrollment_from_another_school(): void
    {
        $managedSchool = School::query()->create(['name' => 'Escola Gerenciada', 'active' => true]);
        $otherSchool = School::query()->create(['name' => 'Outra Escola', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $managedSchool->id);
        $student = $this->personWithRole('Estudante Externo', PersonSchoolRole::ROLE_STUDENT, $otherSchool->id);
        $year = AcademicYear::query()->create([
            'school_id' => $otherSchool->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => '2º Ano',
            'active' => true,
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'school_class_id' => $class->id,
            'person_id' => $student->id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($manager)
            ->get(route('document-issuance.issue', [
                'type' => 'report-card',
                'target_id' => $enrollment->id,
            ]))
            ->assertNotFound();
    }

    public function test_every_target_category_can_be_searched_without_query_errors(): void
    {
        School::query()->create(['name' => 'Escola A', 'active' => true]);
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);

        foreach ([
            'enrollment-declaration',
            'person-record',
            'academic-history',
            'class-schedule',
            'class-report-cards',
            'class-grade-mirror',
            'academic-calendar',
            'school-record',
            'teacher-diary',
        ] as $type) {
            $this->actingAs($administrator)
                ->getJson(route('document-issuance.targets', ['type' => $type]))
                ->assertOk()
                ->assertJsonStructure(['targets']);
        }
    }

    private function userWithRole(string $role, ?int $schoolId = null): User
    {
        $person = $this->personWithRole('Usuário '.$role, $role, $schoolId);

        return User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->institutional_email,
        ]);
    }

    private function personWithRole(string $name, string $role, ?int $schoolId): Person
    {
        $this->personSequence++;
        $person = Person::query()->create([
            'full_name' => $name,
            'institutional_email' => 'pessoa'.$this->personSequence.'@ctjj.org',
            'cpf' => str_pad((string) $this->personSequence, 11, '0', STR_PAD_LEFT),
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria de Teste',
            'phone' => '(66) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoréu',
            'state' => 'MT',
            'postal_code' => '78800-000',
            'profile_completed_at' => now(),
            'active' => true,
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'active' => true,
            'started_at' => '2026-01-01',
        ]);

        return $person;
    }
}
