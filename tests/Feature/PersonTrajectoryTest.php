<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassComponentSubstitution;
use App\Models\User;
use App\Support\PersonTrajectory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PersonTrajectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_trajectory_shows_reclassification_windows_credit_periods_and_closed_year_without_extending_until_today(): void
    {
        app()->setLocale('pt_BR');
        $school = School::create(['name' => 'Escola']);
        [$class, $component, $first, $second] = $this->classroom($school, 2022, '1º Ano');
        $destinationClass = $class->academicYear->classes()->create(['name' => '2º Ano']);
        $destinationClass->courses()->attach($component->course);
        $person = Person::create(['full_name' => 'Estudante da trajetória']);
        $origin = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2022-02-01',
            'status' => 'reclassificado', 'reclassified_at' => '2022-04-25']);
        $origin->courses()->attach($component->course);
        $destination = $destinationClass->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2022-04-25',
            'status' => 'matriculado', 'reclassified_at' => '2022-04-25', 'reclassified_from_enrollment_id' => $origin->id]);
        $destination->courses()->attach($component->course);
        $destination->periodConvalidations()->create(['academic_period_id' => $first->id, 'curriculum_component_id' => $component->id, 'score' => 8]);
        $viewer = $this->viewer('administrador');
        $before = AuditLog::count();
        $result = app(PersonTrajectory::class)->build($person, $viewer, $this->today());
        $this->assertSame($before, AuditLog::count());
        $rows = $result['groups']['estudos']['entries']->keyBy('id');
        $this->assertSame('2022-04-24', $rows['enrollment-'.$origin->id]['end']);
        $this->assertSame('2022-04-25', $rows['enrollment-'.$destination->id]['start']);
        $this->assertSame('2022-12-20', $rows['enrollment-'.$destination->id]['end']);
        $this->assertSame('Ano letivo encerrado', $rows['enrollment-'.$destination->id]['status']);
        $this->assertFalse($rows['enrollment-'.$destination->id]['ongoing']);
        $this->assertStringContainsString('I Bimestre', $rows['enrollment-'.$origin->id]['details'][0]);
        $this->assertStringNotContainsString('II Bimestre', $rows['enrollment-'.$origin->id]['details'][0]);
        $this->assertStringContainsString('II Bimestre', $rows['enrollment-'.$destination->id]['details'][0]);
        $this->assertContains('Períodos aproveitados da origem: I Bimestre', $rows['enrollment-'.$destination->id]['details']);
        $this->assertSame(route('enrollments.report-card.show', $destination), $rows['enrollment-'.$destination->id]['url']);
    }

    public function test_work_history_retains_past_positions_and_does_not_invent_duration_when_dates_are_missing(): void
    {
        app()->setLocale('pt_BR');
        $school = School::create(['name' => 'Escola']);
        $person = Person::create(['full_name' => 'Profissional']);
        $past = $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'funcionário', 'position' => 'Monitor',
            'active' => false, 'started_at' => '2018-01-01', 'ended_at' => '2019-12-31']);
        $current = $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'gestor', 'position' => 'secretário',
            'active' => true, 'started_at' => '2025-01-01']);
        $unknown = $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'professor', 'active' => false]);
        $future = $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'funcionário', 'active' => true, 'started_at' => '2027-01-01']);
        $result = app(PersonTrajectory::class)->build($person, $this->viewer('administrador'), $this->today());
        $rows = $result['groups']['trabalho']['entries']->keyBy('id');
        $this->assertSame('Monitor', $rows['role-'.$past->id]['title']);
        $this->assertSame('2 anos', $rows['role-'.$past->id]['duration']);
        $this->assertTrue($rows['role-'.$current->id]['ongoing']);
        $this->assertSame('Secretaria', $rows['role-'.$current->id]['title']);
        $this->assertSame('Duração não determinada', $rows['role-'.$unknown->id]['duration']);
        $this->assertStringContainsString('término não informado', $rows['role-'.$unknown->id]['period']);
        $this->assertSame('Ainda não iniciado', $rows['role-'.$future->id]['duration']);
        $this->assertSame('trabalho', $result['default_tab']);
    }

    public function test_teaching_groups_subjects_per_class_and_preserves_dated_substitutions_instead_of_claiming_employment_dates(): void
    {
        $school = School::create(['name' => 'Escola']);
        [$class, $math] = $this->classroom($school, 2024, '6º Ano A');
        $art = $math->course->components()->create(['name' => 'Arte', 'active' => true]);
        $person = Person::create(['full_name' => 'Docente']);
        foreach ([$math, $art] as $component) {
            SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'teacher_person_id' => $person->id, 'active' => true]);
        }
        [$other, $subject] = $this->classroom($school, 2024, '7º Ano B');
        $assignment = SchoolClassComponent::create(['school_class_id' => $other->id, 'curriculum_component_id' => $subject->id, 'active' => true]);
        SchoolClassComponentSubstitution::create(['school_class_component_id' => $assignment->id, 'substitute_teacher_person_id' => $person->id,
            'starts_at' => '2024-06-01', 'ends_at' => '2024-06-30']);
        SchoolClassComponentSubstitution::create(['school_class_component_id' => $assignment->id, 'substitute_teacher_person_id' => $person->id,
            'starts_at' => '2024-08-01']);
        $result = app(PersonTrajectory::class)->build($person, $this->viewer('administrador'), $this->today());
        $rows = $result['groups']['docencia']['entries'];
        $this->assertCount(3, $rows);
        $group = $rows->firstWhere('mode', 'assigned');
        $this->assertSame('Arte · Matemática', $group['subtitle']);
        $this->assertSame('Referência do calendário letivo', $group['duration']);
        $sub = $rows->firstWhere('start', '2024-06-01');
        $this->assertSame('2024-06-30', $sub['end']);
        $open = $rows->firstWhere('start', '2024-08-01');
        $this->assertSame('2024-12-20', $open['end']);
        $this->assertFalse($open['ongoing']);
        $this->assertSame('docencia', $result['default_tab']);
    }

    public function test_person_page_includes_archived_records_and_keeps_school_scopes_separate(): void
    {
        $school = School::create(['name' => 'Escola permitida']);
        $other = School::create(['name' => 'Escola restrita']);
        $person = Person::create(['full_name' => 'Pessoa com trajetória']);
        foreach ([$school, $other] as $s) {
            $person->schoolRoles()->create(['school_id' => $s->id, 'role' => 'funcionário', 'active' => false,
                'started_at' => '2022-02-01', 'ended_at' => '2022-12-20']);
            [$class, $component] = $this->classroom($s, 2022, $s->id === $school->id ? 'Turma permitida' : 'Turma restrita');
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2022-02-01', 'status' => 'transferido', 'transferred_at' => '2022-09-10']);
            $enrollment->courses()->attach($component->course);
            SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'teacher_person_id' => $person->id, 'active' => false]);
        }
        $this->actingAs($this->viewer('gestor', $school->id))->get(route('people.show', $person))->assertOk()
            ->assertSee('Trajetória e movimentações')->assertSee('Vínculos profissionais')->assertSee('Docência')
            ->assertSee('Turma Permitida')->assertSee('10/09/2022')->assertDontSee('Escola Restrita')->assertDontSee('Turma Restrita');
        $this->actingAs($this->viewer('administrador'))->get(route('people.show', $person))->assertOk()->assertSee('Turma Restrita');
        $this->actingAs($this->viewer('professor', $school->id))->get(route('people.show', $person))->assertForbidden();
    }

    public function test_pdf_uses_the_complete_trajectory_and_respects_the_same_school_permissions(): void
    {
        $school = School::create(['name' => 'Escola Permitida', 'legal_name' => 'Instituição de Teste', 'cnpj' => '00176974000120',
            'inep' => '51061716', 'founded_at' => '1990-01-01', 'phone' => '66999999999', 'email' => 'escola@example.test',
            'letterhead_text' => 'Escola fictícia de teste', 'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000']);
        $other = $school->replicate();
        $other->name = 'Escola Restrita';
        $other->inep = '51061717';
        $other->cnpj = '00176974000200';
        $other->save();
        $target = $this->viewer('professor', $school->id)->person;
        $target->schoolRoles()->create(['school_id' => $other->id, 'role' => 'funcionário', 'position' => 'Monitoria',
            'started_at' => '2018-01-01', 'ended_at' => '2019-12-31', 'active' => false]);
        foreach ([[$school, 'Turma Permitida'], [$other, 'Turma Restrita']] as [$s, $name]) {
            [$class, $component] = $this->classroom($s, 2022, $name);
            $e = $class->enrollments()->create(['person_id' => $target->id, 'enrolled_at' => '2022-02-01', 'transferred_at' => '2022-09-10', 'status' => 'transferido']);
            $e->courses()->attach($component->course);
            SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'teacher_person_id' => $target->id, 'active' => false]);
        }
        $data = null;
        View::composer('reports.records.person', function ($view) use (&$data): void {
            $data = $view->getData();
        });
        $response = $this->actingAs($this->viewer('administrador'))->get(route('people.pdf', $target))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('reports.records.person', $data)->render();
        foreach (['Trajetória e movimentações', 'Estudos', 'Vínculos profissionais', 'Docência', 'Turma Permitida', 'Turma Restrita', 'Monitoria', '10/09/2022', '2 anos'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->actingAs($this->viewer('gestor', $school->id))->get(route('people.pdf', $target))->assertOk();
        $html = view('reports.records.person', $data)->render();
        $this->assertStringContainsString('Turma Permitida', $html);
        $this->assertStringNotContainsString('Turma Restrita', $html);
        $this->assertStringNotContainsString('Escola Restrita', $html);
    }

    public function test_older_student_role_is_retained_when_only_a_later_enrollment_exists_at_the_school(): void
    {
        $school = School::create(['name' => 'Escola']);
        [$class, $component] = $this->classroom($school, 2022, '1º Ano');
        $person = Person::create(['full_name' => 'Estudante com vínculo antigo']);
        $old = $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'aluno', 'active' => false,
            'started_at' => '2010-02-01', 'ended_at' => '2015-12-20']);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => 'aluno', 'active' => false,
            'started_at' => '2022-02-01', 'ended_at' => '2022-12-20']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2022-02-01', 'status' => 'matriculado']);
        $enrollment->courses()->attach($component->course);
        $rows = app(PersonTrajectory::class)->build($person, $this->viewer('administrador'), $this->today())['groups']['estudos']['entries'];
        $this->assertCount(2, $rows);
        $this->assertSame('2010-02-01', $rows->firstWhere('id', 'role-'.$old->id)['start']);
        $this->assertSame(['Série e turma não informadas no vínculo.'], $rows->firstWhere('id', 'role-'.$old->id)['details']);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-14', 'America/Campo_Grande');
    }

    private function viewer(string $role, ?int $schoolId = null): User
    {
        $person = Person::create(['full_name' => 'Visualizador '.$role, 'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Mãe de Teste', 'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78800000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $schoolId, 'active' => true, 'started_at' => '2020-01-01']);

        return User::factory()->create(['person_id' => $person->id, 'locale' => 'pt_BR']);
    }

    private function classroom(School $school, int $number, string $name): array
    {
        $year = AcademicYear::firstOrCreate(['school_id' => $school->id, 'reference_year' => $number], ['name' => 'Educação Básica',
            'starts_at' => $number.'-02-01', 'ends_at' => $number.'-12-20', 'closed_at' => $number.'-12-21']);
        $first = $year->periods()->firstOrCreate(['position' => 1], ['name' => 'I Bimestre', 'starts_at' => $number.'-02-01', 'ends_at' => $number.'-04-24']);
        $second = $year->periods()->firstOrCreate(['position' => 2], ['name' => 'II Bimestre', 'starts_at' => $number.'-04-25', 'ends_at' => $number.'-12-20']);
        $course = $year->courses()->create(['name' => $name, 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $component = $course->components()->create(['name' => 'Matemática', 'active' => true]);
        $class = $year->classes()->create(['name' => $name, 'active' => true]);
        $class->courses()->attach($course);

        return [$class, $component, $first, $second];
    }
}
