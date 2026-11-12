<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\LegacyLiceuItineraryLinks;
use App\Support\StudentReportCardBuilder;
use App\Support\StudentTechnicalItinerary;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalItineraryTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $school = School::create(['name' => 'Escola de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'active' => true]);
        $actor = Person::create(['full_name' => 'Gestora de teste', 'cpf' => '12345678901', 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Responsável', 'address' => 'Rua', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $actor->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $actor->id]));
        $person = Person::create(['full_name' => 'Estudante de teste']);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_STUDENT, 'active' => true]);
        $regular = $this->enrollment($school, $person, '3º Ano', 'medio', 2026);
        $technical = $this->enrollment($school, $person, 'Técnico em Móveis', 'tecnico', 2025, 2026);

        return [$school, $person, $regular, $technical];
    }

    private function enrollment(School $school, Person $person, string $name, string $stage, int $year, ?int $ends = null): StudentEnrollment
    {
        $calendar = AcademicYear::create(['school_id' => $school->id, 'name' => $name.' '.$year, 'reference_year' => $year, 'starts_at' => "$year-02-01", 'ends_at' => ($ends ?? $year).'-12-20', 'active' => true, 'class_hour_minutes' => 60]);
        $course = $calendar->courses()->create(['name' => $name, 'stage' => $stage, 'modality' => 'regular', 'class_hour_minutes' => 60, 'active' => true]);
        $class = $calendar->classes()->create(['name' => $name, 'active' => true]);
        $class->courses()->attach($course);
        foreach (array_unique([$year, $ends ?? $year]) as $position => $periodYear) {
            $period = $calendar->periods()->create(['name' => 'Módulo '.($position + 1), 'position' => $position + 1, 'starts_at' => "$periodYear-02-01", 'ends_at' => "$periodYear-12-20"]);
            $course->components()->create(['name' => $name.' componente '.$periodYear, 'workload_hours' => 100, 'starts_period_id' => $period->id, 'ends_period_id' => $period->id, 'active' => true]);
        }
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'status' => 'matriculado', 'enrolled_at' => "$year-02-01", 'type' => 'regular']);
        $enrollment->courses()->attach($course);

        return $enrollment;
    }

    public function test_calendar_overlap_does_not_create_an_itinerary_and_independent_courses_have_separate_histories(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $other = $this->enrollment($school, $person, 'Técnico em Informática', 'tecnico', 2026);
        $report = app(StudentReportCardBuilder::class)->build($regular);
        $this->assertCount(1, $report['courses']);
        $this->post(route('student-histories.unified', [$person, 'tecnico']))->assertSessionHasErrors('technical_course_id');
        $histories = [];
        foreach ([$technical, $other] as $enrollment) {
            $course = $enrollment->courses()->first();
            $history = app(UnifiedStudentHistorySynchronizer::class)->synchronize($person, $school->id, auth()->user()->person_id, 'tecnico', $course->id);
            $histories[] = $history;
            $this->assertSame($course->id, $history->technical_course_id);
            $this->assertSame([$enrollment->id], $history->years->pluck('student_enrollment_id')->all());
            $this->assertSame($course->components()->count(), $history->years->first()->records()->count());
            $this->assertStringContainsString($course->name, $history->title);
        }
        $this->assertNotSame($histories[0]->id, $histories[1]->id);
        $this->assertSame(2, StudentAcademicHistory::forIssuance()->count());
        $this->get(route('student-histories.student', $person))->assertOk()->assertSee('Técnico em Informática')->assertSee('Técnico em Móveis');
    }

    public function test_explicit_itinerary_is_allocated_by_module_completion_year_and_cannot_be_issued_separately(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $previous = $this->enrollment($school, $person, '2º Ano', 'medio', 2025);
        $course = $technical->courses()->first();
        $standalone = app(UnifiedStudentHistorySynchronizer::class)->synchronize($person, $school->id, auth()->user()->person_id, 'tecnico', $course->id);
        foreach ([$previous, $regular] as $enrollment) {
            $enrollment->courses()->first()->technicalItineraries()->attach($course);
        }
        $history = app(UnifiedStudentHistorySynchronizer::class)->synchronize($person, $school->id, auth()->user()->person_id, 'medio');
        foreach ([2025 => $previous, 2026 => $regular] as $year => $enrollment) {
            $records = $history->years->firstWhere('student_enrollment_id', $enrollment->id)->records;
            $this->assertCount(2, $records);
            $this->assertContains('Técnico em Móveis componente '.$year, $records->map(fn ($r) => $r->component->name)->all());
            $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
            $this->assertCount(2, $report['courses']);
            $this->assertCount(2, $report['annualComponents']);
        }
        $this->assertFalse(app(StudentTechnicalItinerary::class)->canIssue($standalone));
        $this->assertFalse(StudentAcademicHistory::forIssuance()->whereKey($standalone->id)->exists());
        $this->get(route('people.histories.pdf', [$person, $standalone]))->assertUnprocessable();
        $this->get(route('document-issuance.issue', ['type' => 'academic-history', 'target_id' => $standalone->id]))->assertNotFound();
        $this->getJson(route('document-issuance.targets', ['type' => 'academic-history']))->assertOk()->assertJsonMissing(['id' => $standalone->id]);
        $this->post(route('student-histories.unified', [$person, 'tecnico']), ['technical_course_id' => $course->id])->assertSessionHasErrors();
        $this->assertDatabaseHas('student_academic_histories', ['id' => $standalone->id]);
        $this->get('/pessoas/'.$person->id.'/itinerarios')->assertNotFound();
        $this->get(route('student-histories.student', $person))->assertOk()->assertDontSee('Definir itinerário do estudante');
    }

    public function test_integrated_technical_components_keep_the_existing_matrix_and_bimester_configuration(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $course = $technical->courses()->first();
        $regular->courses()->attach($course);
        $regular->schoolClass->courses()->attach($course);
        $report = app(StudentReportCardBuilder::class)->build($regular->fresh());
        $this->assertCount(2, $report['courses']);
        $this->assertCount(3, $report['annualComponents']);
        $this->assertTrue(app(StudentTechnicalItinerary::class)->independentCourses($person)->isEmpty());
        $history = app(UnifiedStudentHistorySynchronizer::class)->synchronize($person, $school->id, auth()->user()->person_id, 'medio');
        $this->assertCount(2, $history->years->first()->records);
        $this->assertNull($regular->fresh()->technical_itinerary_course_id);
    }

    public function test_matrix_itinerary_applies_to_all_students_and_does_not_borrow_another_students_grades(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $matrix = $regular->courses()->first();
        $course = $technical->courses()->first();
        $route = route('academic-years.courses.itinerary.update', [$matrix->academicYear, $matrix]);
        $other = Person::create(['full_name' => 'Outro estudante']);
        $second = $regular->schoolClass->enrollments()->create(['person_id' => $other->id, 'status' => 'matriculado', 'enrolled_at' => '2026-02-01']);
        $second->courses()->attach($matrix);
        $this->put($route, ['itinerary_kind' => 'technical', 'technical_itinerary_ids' => [$course->id]])->assertSessionHasNoErrors()->assertRedirect();
        $legacyCourse = $this->enrollment($school, $person, 'Outro técnico independente', 'tecnico', 2026)->courses()->first();
        $regular->forceFill(['technical_itinerary_course_id' => $legacyCourse->id])->save();
        $report = app(StudentReportCardBuilder::class)->build($regular->fresh());
        $this->assertCount(2, $report['courses']);
        $this->assertFalse($report['courses']->contains('id', $legacyCourse->id));
        $this->assertCount(1, app(StudentReportCardBuilder::class)->build($second->fresh())['courses']);
        $technical->schoolClass->enrollments()->create(['person_id' => $other->id, 'status' => 'matriculado', 'enrolled_at' => '2025-02-01'])->courses()->attach($course);
        $this->assertCount(2, app(StudentReportCardBuilder::class)->build($second->fresh())['courses']);
        $this->assertNull($second->fresh()->technical_itinerary_course_id);
        $this->put($route, ['itinerary_kind' => 'own', 'itinerary_name' => 'Projetos'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertCount(1, app(StudentReportCardBuilder::class)->build($regular->fresh())['courses']);
        $this->assertTrue(app(StudentTechnicalItinerary::class)->independentCourses($person)->contains('id', $course->id));
    }

    public function test_offers_and_submission_respect_school_dates_and_matrix_permissions(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $matrix = $regular->courses()->first();
        $course = $technical->courses()->first();
        $outside = $this->enrollment($school, $person, 'Curso fora das datas', 'tecnico', 2023);
        $otherSchool = School::create(['name' => 'Outra escola']);
        $foreign = $this->enrollment($otherSchool, $person, 'Curso de outra escola', 'tecnico', 2026);
        $screen = $this->get(route('academic-years.courses.show', [$matrix->academicYear, $matrix]))->assertOk();
        $screen->assertSee('Curso técnico já cadastrado')->assertSee('Técnico em Móveis')->assertSee('1 de 1 alunos já matriculados');
        $screen->assertDontSee('Curso fora das datas')->assertDontSee('Curso de outra escola');
        $route = route('academic-years.courses.itinerary.update', [$matrix->academicYear, $matrix]);
        foreach ([$outside, $foreign] as $invalid) {
            $this->put($route, ['itinerary_kind' => 'technical', 'technical_itinerary_ids' => [$invalid->courses()->first()->id]])->assertSessionHasErrors('technical_itinerary_ids');
        }
        $this->assertCount(0, $matrix->technicalItineraries);
        $matrix->academicYear->update(['closed_at' => '2026-12-20']);
        $this->put($route, ['itinerary_kind' => 'technical', 'technical_itinerary_ids' => [$course->id]])->assertSessionHasErrors('itinerary_kind');
        $unmanaged = $this->enrollment($otherSchool, $person, '3º Ano outra escola', 'medio', 2026)->courses()->first();
        $this->put(route('academic-years.courses.itinerary.update', [$unmanaged->academicYear, $unmanaged]), ['itinerary_kind' => 'technical', 'technical_itinerary_ids' => [$foreign->courses()->first()->id]])->assertForbidden();
        auth()->user()->person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(auth()->user()->fresh());
        $this->put($route, ['itinerary_kind' => 'technical', 'technical_itinerary_ids' => [$course->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $matrix->technicalItineraries()->count());
    }

    public function test_old_manual_technical_years_require_course_identification_and_keep_their_values_and_ids(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $other = $this->enrollment($school, $person, 'Técnico em Informática', 'tecnico', 2026);
        $legacy = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id,
            'title' => 'Histórico antigo', 'education_stage' => 'tecnico', 'is_unified' => true, 'active' => true]);
        $year = $legacy->years()->create(['source' => 'manual', 'label' => 'Módulo anterior', 'stage' => AcademicCourse::STAGE_LABELS['tecnico'], 'year' => '2024', 'workload_hours' => 200]);
        $component = $legacy->components()->create(['name' => 'Prática profissional']);
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_numeric' => 8, 'workload_hours' => 200]);
        $this->get(route('student-histories.student', $person))->assertOk()->assertSee('Identificar cursos');
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $year->id, 'student_academic_history_id' => $legacy->id]);
        $course = $technical->courses()->first();
        $this->post(route('people.histories.identify-course', [$person, $legacy]), ['technical_course_id' => $course->id])->assertSessionHasNoErrors()->assertRedirect();
        $target = StudentAcademicHistory::where('person_id', $person->id)->where('is_unified', true)->where('technical_course_id', $course->id)->sole();
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $year->id, 'student_academic_history_id' => $target->id, 'workload_hours' => 200]);
        $this->assertDatabaseHas('student_academic_history_records', ['id' => $record->id, 'score_numeric' => 8, 'workload_hours' => 200]);
    }

    public function test_authorized_backfill_changes_only_the_current_third_year_cohort_and_its_previous_year(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $school->update(['name' => LegacyLiceuItineraryLinks::SCHOOL_NAME]);
        $previous = $this->enrollment($school, $person, '2º Ano', 'medio', 2025);
        $unaffectedStudent = Person::create(['full_name' => 'Estudante do primeiro ano']);
        $first = $this->enrollment($school, $unaffectedStudent, '1º Ano', 'medio', 2026);
        $integrated = $this->enrollment($school, $unaffectedStudent, 'Técnico em Móveis', 'tecnico', 2026);
        $first->courses()->attach($integrated->courses()->first());
        $snapshot = app(LegacyLiceuItineraryLinks::class)->authorizedThirdYearSnapshot();
        $this->assertSame(1, $snapshot['students']);
        $this->assertSame(2, $snapshot['count']);
        $before = $first->fresh()->toArray();
        $matrixBefore = $first->courses()->first()->fresh()->toArray();
        $this->artisan('itineraries:link-liceu-third-year', ['--apply' => true, '--expected' => str_repeat('0', 64)])->assertFailed();
        $this->assertNull($regular->fresh()->technical_itinerary_course_id);
        $this->artisan('itineraries:link-liceu-third-year', ['--apply' => true, '--expected' => $snapshot['sha256']])->assertSuccessful();
        $this->artisan('itineraries:link-liceu-third-year', ['--apply' => true, '--expected' => $snapshot['sha256']])->assertSuccessful();
        foreach ([$regular, $previous] as $enrollment) {
            $this->assertNull($enrollment->fresh()->technical_itinerary_course_id);
            $this->assertSame(1, $enrollment->courses()->first()->technicalItineraries()->count());
        }
        $this->assertSame($before, $first->fresh()->toArray());
        $this->assertSame($matrixBefore, $first->courses()->first()->fresh()->toArray());
        $this->assertDatabaseCount('academic_course_technical_itinerary', 2);
    }

    public function test_high_school_matrix_requires_an_internal_or_technical_itinerary(): void
    {
        [$school, $person, $regular, $technical] = $this->scenario();
        $data = ['name' => 'Novo Médio', 'stage' => 'medio', 'modality' => 'regular', 'class_hour_minutes' => 60];
        $route = route('academic-years.courses.store', $regular->schoolClass->academicYear);
        $this->post($route, $data)->assertSessionHasErrors('itinerary_name');
        $this->post($route, $data + ['itinerary_name' => 'Ciências da Natureza'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($route, array_merge($data, ['name' => 'Médio técnico', 'technical_itinerary_ids' => [$technical->courses()->first()->id]]))->assertSessionHasNoErrors()->assertRedirect();
    }
}
