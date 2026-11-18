<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\UserOperationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentPartialTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_partial_in_each_component_allows_transfer_with_other_assessments_unfilled_and_preserves_grades(): void
    {
        $s = $this->scenario();
        foreach ($s['assessments'] as $assessment) {
            $assessment->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 7.5]);
        }
        $before = DiaryAssessmentResult::orderBy('id')->get()->toArray();
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(StudentEnrollment::STATUS_TRANSFERRED, $s['enrollment']->fresh()->status);
        $this->assertSame($before, DiaryAssessmentResult::orderBy('id')->get()->toArray());
    }

    public function test_one_partial_overall_does_not_cover_another_component_without_any_grade_and_logs_the_reason(): void
    {
        $s = $this->scenario();
        $s['assessments'][0]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 8]);
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasErrors('transferred_at');
        $this->assertSame(StudentEnrollment::STATUS_ENROLLED, $s['enrollment']->fresh()->status);
        $failure = UserOperationFailure::sole();
        $this->assertStringContainsString('Português', $failure->message);
        $this->assertSame('Registrar transferência de estudante', $failure->operation);
        $this->assertSame($s['enrollment']->id, $failure->context['enrollment']['id']);
        $this->assertSame($s['student']->full_name, $failure->context['estudante']['nome']);
        $this->assertSame('2026-03-15', $failure->context['transferred_at']);
    }

    public function test_zero_is_a_recorded_grade_but_a_null_result_or_another_students_grade_is_not(): void
    {
        $s = $this->scenario();
        $s['assessments'][0]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 0]);
        $s['assessments'][1]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => null]);
        $other = Person::create(['full_name' => 'Outro Estudante Fictício']);
        $otherEnrollment = $s['class']->enrollments()->create(['person_id' => $other->id, 'enrolled_at' => '2026-02-01', 'status' => 'matriculado']);
        $s['assessments'][1]->results()->create(['student_enrollment_id' => $otherEnrollment->id, 'score' => 8]);
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasErrors('transferred_at');
        $s['assessments'][1]->results()->where('student_enrollment_id', $s['enrollment']->id)->update(['score' => 0]);
        $this->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_inactive_components_and_components_that_start_in_a_later_period_do_not_block_transfer(): void
    {
        $s = $this->scenario();
        $s['assessments'][0]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 8]);
        $s['components'][1]->update(['active' => false]);
        $later = $s['year']->periods()->create(['name' => '2º Bimestre', 'position' => 2, 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30']);
        $s['course']->components()->create(['name' => 'Projeto posterior', 'active' => true, 'starts_period_id' => $later->id]);
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_a_dated_manual_assessment_after_exit_does_not_supply_a_partial_grade(): void
    {
        $s = $this->scenario();
        $s['assessments'][0]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 8]);
        $s['assessments'][1]->update(['school_assessment_rule_id' => null, 'assessment_date' => '2026-03-20']);
        $s['assessments'][1]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 9]);
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasErrors('transferred_at');
    }

    public function test_an_existing_convalidated_grade_is_preserved_and_satisfies_the_partial_requirement(): void
    {
        $s = $this->scenario();
        $s['assessments'][0]->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 8]);
        $credit = $s['enrollment']->periodConvalidations()->create(['academic_period_id' => $s['period']->id, 'curriculum_component_id' => $s['components'][1]->id, 'score' => 7, 'source_school' => 'Escola de origem']);
        $before = $credit->fresh()->getAttributes();
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before, $credit->fresh()->getAttributes());
    }

    public function test_transfer_error_lists_each_missing_component_and_its_teacher_without_truncation(): void
    {
        $s = $this->scenario();
        $teacher = Person::create(['full_name' => 'Professora Maria Aparecida da Silva']);
        SchoolClassComponent::create(['school_class_id' => $s['class']->id, 'curriculum_component_id' => $s['components'][1]->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        for ($i = 1; $i <= 5; $i++) {
            $s['course']->components()->create(['name' => 'Componente pendente '.$i, 'active' => true]);
        }
        $response = $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasErrors('transferred_at');
        $messages = session('errors')->get('transferred_at');
        $this->assertCount(8, $messages);
        $this->assertStringContainsString($s['student']->full_name, $messages[0]);
        $this->assertStringContainsString('Português — Professor: Professora Maria Aparecida da Silva', implode(' ', $messages));
        $this->assertStringContainsString('Matemática — Professor não associado à turma', implode(' ', $messages));
        $this->assertStringContainsString('Componente pendente 5', implode(' ', $messages));
        $this->get(route('classes.enrollments.index', $s['class']))->assertOk()->assertSee('Português — Professor: Professora Maria Aparecida da Silva')->assertSee('Componente pendente 5');
    }

    public function test_substitute_teacher_is_only_listed_when_the_transfer_date_is_within_the_substitution(): void
    {
        $s = $this->scenario();
        $main = Person::create(['full_name' => 'Professora Titular Fictícia']);
        $current = Person::create(['full_name' => 'Professora Substituta Vigente']);
        $former = Person::create(['full_name' => 'Professor Substituto Antigo']);
        $assignment = SchoolClassComponent::create(['school_class_id' => $s['class']->id, 'curriculum_component_id' => $s['components'][0]->id, 'teacher_person_id' => $main->id, 'active' => true]);
        $assignment->substitutions()->create(['substitute_teacher_person_id' => $current->id, 'starts_at' => '2026-03-01', 'ends_at' => '2026-03-31']);
        $assignment->substitutions()->create(['substitute_teacher_person_id' => $former->id, 'starts_at' => '2026-02-01', 'ends_at' => '2026-02-28']);
        $this->actingAs($s['user'])->patch(route('enrollments.transfer', $s['enrollment']), $this->fields())->assertSessionHasErrors('transferred_at');
        $messages = implode(' ', session('errors')->get('transferred_at'));
        $this->assertStringContainsString($main->full_name, $messages);
        $this->assertStringContainsString($current->full_name.' (substituição)', $messages);
        $this->assertStringNotContainsString($former->full_name, $messages);
    }

    private function fields(): array
    {
        return ['transferred_at' => '2026-03-15', 'confirm_transfer' => '1'];
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $school = School::create(['name' => 'Escola Laura Vicuña de Teste', 'active' => true]);
        $person = Person::create(['full_name' => 'Gestor Fictício da Transferência', 'cpf' => '12345678901', 'birth_date' => '1980-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria Fictícia', 'address' => 'Rua Teste', 'phone' => '66999990000', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'active' => true, 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'active' => true, 'started_at' => '2020-01-01']);
        $user = User::factory()->create(['person_id' => $person->id]);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20', 'active' => true]);
        $period = $year->periods()->create(['name' => '1º Bimestre', 'position' => 1, 'starts_at' => '2026-02-01', 'ends_at' => '2026-04-10']);
        $course = $year->courses()->create(['name' => '9º Ano', 'stage' => 'fundamental', 'active' => true]);
        $class = $year->classes()->create(['name' => '9º Ano A', 'active' => true]);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante Fictício da Transferência', 'active' => true]);
        $student->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_STUDENT, 'active' => true]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2026-02-01', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        $components = [];
        $assessments = [];
        $rule = $period->assessmentRules()->firstOrFail();
        foreach (['Matemática', 'Português'] as $name) {
            $component = $course->components()->create(['name' => $name, 'active' => true]);
            $components[] = $component;
            $assessments[] = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'school_assessment_rule_id' => $rule->id, 'is_recovery' => false, 'assessment_date' => '2026-04-10', 'title' => 'Nota parcial']);
            DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'is_recovery' => false, 'title' => 'Avaliação ainda sem nota']);
        }

        return compact('school', 'user', 'year', 'period', 'course', 'class', 'student', 'enrollment', 'components', 'assessments');
    }
}
