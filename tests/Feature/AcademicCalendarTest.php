<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriodDiaryConsolidation;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CalendarDay;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\AcademicStructureValidator;
use App\Support\AcademicYearClosureStatus;
use App\Support\CurriculumCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_day_count_is_not_compared_with_a_fixed_200_day_warning(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'minimum_school_days' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.show', $year))
            ->assertOk()
            ->assertDontSee('Dias letivos abaixo do mínimo')
            ->assertDontSee('Mínimo legal')
            ->assertDontSee('mínimo 200');

        $structureIssues = collect(AcademicStructureValidator::forAcademicYear($year));
        $closureIssues = collect((new AcademicYearClosureStatus)->issues($year));

        $this->assertFalse($structureIssues->contains(
            fn (array $issue): bool => str_contains($issue['title'], 'Dias letivos abaixo do mínimo')
        ));
        $this->assertFalse($closureIssues->contains(
            fn (array $issue): bool => str_contains($issue['message'], 'mínimo de dias letivos')
        ));
    }

    public function test_administrator_can_create_academic_year_inside_school(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create($this->officialSchoolData(['name' => 'Escola A']));

        $this->actingAs($admin)
            ->post(route('schools.academic-years.store', $school), [
                'name' => 'Educação Básica',
                'reference_year' => 2026,
                'starts_at' => '2026-01-20',
                'ends_at' => '2026-12-18',
                'class_hour_minutes' => 50,
                'active' => '1',
            ])
            ->assertRedirect(route('schools.academic-years.index', $school));

        $this->assertDatabaseHas('academic_years', [
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'class_hour_minutes' => 50,
        ]);
    }

    public function test_academic_year_creation_generates_all_days_as_vacation(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->post(route('schools.academic-years.store', $school), [
                'name' => 'Educação Básica',
                'reference_year' => 2026,
                'starts_at' => '2026-01-05',
                'ends_at' => '2026-01-11',
                'active' => '1',
            ])
            ->assertRedirect(route('schools.academic-years.index', $school));

        $year = AcademicYear::query()->firstOrFail();

        $this->assertSame(7, $year->days()->count());
        $this->assertSame(0, $year->days()->where('counts_as_school_day', true)->count());
        $this->assertSame(5, $year->days()->where('type', CalendarDay::TYPE_FINAL_VACATION)->count());
        $this->assertSame(2, $year->days()->where('type', CalendarDay::TYPE_WEEKEND)->count());
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-07',
            'type' => CalendarDay::TYPE_FINAL_VACATION,
            'counts_as_school_day' => false,
            'title' => 'Férias',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-10',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
            'title' => null,
        ]);
    }

    public function test_academic_period_creation_can_ignore_only_saturdays(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'starts_at' => '2026-01-10',
            'ends_at' => '2026-01-11',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-01-10',
                'ends_at' => '2026-01-11',
                'position' => 1,
                'ignore_saturdays' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-10 00:00:00',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-11 00:00:00',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
    }

    public function test_academic_period_creation_can_ignore_only_sundays(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'starts_at' => '2026-01-10',
            'ends_at' => '2026-01-11',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-01-10',
                'ends_at' => '2026-01-11',
                'position' => 1,
                'ignore_sundays' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-10 00:00:00',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-11 00:00:00',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
        ]);
    }

    public function test_days_between_academic_periods_become_recess(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-02-20',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-02-02',
                'ends_at' => '2026-02-06',
                'position' => 1,
                'ignore_saturdays' => '1',
                'ignore_sundays' => '1',
            ])
            ->assertRedirect();

        CalendarDay::query()->updateOrCreate(
            [
                'academic_year_id' => $year->id,
                'date' => '2026-02-11',
            ],
            [
                'type' => CalendarDay::TYPE_HOLIDAY,
                'counts_as_school_day' => false,
                'title' => 'Feriado',
            ]
        );

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '2º Bimestre',
                'starts_at' => '2026-02-16',
                'ends_at' => '2026-02-20',
                'position' => 2,
                'ignore_saturdays' => '1',
                'ignore_sundays' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-09 00:00:00',
            'type' => CalendarDay::TYPE_RECESS,
            'counts_as_school_day' => false,
            'title' => 'Recesso escolar',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-11 00:00:00',
            'type' => CalendarDay::TYPE_HOLIDAY,
            'counts_as_school_day' => false,
            'title' => 'Feriado',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-14 00:00:00',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
        ]);
    }

    public function test_removing_last_academic_period_resets_all_school_days(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-02-10',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-02-02',
                'ends_at' => '2026-02-06',
                'position' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(5, $year->fresh()->schoolDayCount());

        $period = $year->periods()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('academic-years.periods.destroy', [$year, $period]))
            ->assertRedirect(route('academic-years.periods.index', $year));

        $year->refresh();

        $this->assertSame(0, $year->schoolDayCount());
        $this->assertSame(
            0,
            $year->days()->where('type', CalendarDay::TYPE_SCHOOL_DAY)->count()
        );
    }

    public function test_academic_year_can_be_approved_after_creation(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $this->actingAs($admin)
            ->patch(route('academic-years.approve', $year), [
                'approved_at' => '2025-12-10',
            ])
            ->assertRedirect(route('academic-years.show', $year));

        $this->assertDatabaseHas('academic_years', [
            'id' => $year->id,
            'approved_at' => '2025-12-10 00:00:00',
        ]);
    }

    public function test_academic_periods_cannot_overlap(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-30',
            'position' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '2º Bimestre',
                'starts_at' => '2026-04-15',
                'ends_at' => '2026-06-30',
                'position' => 2,
            ])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_academic_period_can_be_updated_and_calendar_days_are_rebuilt(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-02-15',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-02-02',
                'ends_at' => '2026-02-06',
                'position' => 1,
                'ignore_saturdays' => '1',
                'ignore_sundays' => '1',
            ])
            ->assertRedirect();

        $period = $year->periods()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('academic-years.periods.update', [$year, $period]), [
                'name' => 'I Bimestre',
                'starts_at' => '2026-02-04',
                'ends_at' => '2026-02-10',
                'position' => 2,
                'ignore_saturdays' => '1',
                'ignore_sundays' => '1',
                'notes' => 'Período ajustado pela gestão.',
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('academic_periods', [
            'id' => $period->id,
            'name' => 'I Bimestre',
            'starts_at' => '2026-02-04 00:00:00',
            'ends_at' => '2026-02-10 00:00:00',
            'position' => 2,
            'notes' => 'Período ajustado pela gestão.',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-03 00:00:00',
            'type' => CalendarDay::TYPE_FINAL_VACATION,
            'counts_as_school_day' => false,
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-04 00:00:00',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-07 00:00:00',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-02-09 00:00:00',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
    }

    public function test_academic_period_update_cannot_overlap_another_period(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $firstPeriod = $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-30',
            'position' => 1,
        ]);
        $secondPeriod = $year->periods()->create([
            'name' => '2º Bimestre',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-07-10',
            'position' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('academic-years.periods.update', [$year, $secondPeriod]), [
                'name' => '2º Bimestre',
                'starts_at' => '2026-04-15',
                'ends_at' => '2026-07-10',
                'position' => 2,
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseHas('academic_periods', [
            'id' => $firstPeriod->id,
            'ends_at' => '2026-04-30 00:00:00',
        ]);
        $this->assertDatabaseHas('academic_periods', [
            'id' => $secondPeriod->id,
            'starts_at' => '2026-05-01 00:00:00',
        ]);
    }

    public function test_approved_academic_year_can_be_changed_only_by_global_administrator(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestao@ctjj.org');
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, null, 'admin@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('academic-years.periods.store', $year), [
                'name' => '1º Bimestre',
                'starts_at' => '2026-02-01',
                'ends_at' => '2026-04-30',
                'position' => 1,
            ])
            ->assertSessionHasErrors('approved_at');

        $period = $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-30',
            'position' => 1,
        ]);

        $this->actingAs($manager)
            ->put(route('academic-years.periods.update', [$year, $period]), [
                'name' => 'I Bimestre',
                'starts_at' => '2026-02-02',
                'ends_at' => '2026-04-30',
                'position' => 1,
            ])
            ->assertSessionHasErrors('approved_at');

        $this->actingAs($manager)
            ->post(route('academic-years.days.store', $year), [
                'date' => '2026-03-10',
                'type' => CalendarDay::TYPE_HOLIDAY,
                'counts_as_school_day' => '0',
            ])
            ->assertSessionHasErrors('approved_at');

        $this->actingAs($admin)
            ->post(route('academic-years.days.store', $year), [
                'date' => '2026-03-10',
                'type' => CalendarDay::TYPE_HOLIDAY,
                'counts_as_school_day' => '0',
                'title' => 'Feriado',
            ])
            ->assertRedirect(route('academic-years.show', $year));

        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-03-10 00:00:00',
            'type' => CalendarDay::TYPE_HOLIDAY,
        ]);
    }

    public function test_dashboard_shows_announcements_and_calendar_days(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id, 'estudante@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => now()->year,
            'starts_at' => now()->startOfYear()->toDateString(),
            'ends_at' => now()->endOfYear()->toDateString(),
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);

        Announcement::query()->create([
            'school_id' => $school->id,
            'title' => 'Reunião Geral',
            'body' => 'Atenção ao recado.',
            'starts_at' => now()->subHour(),
            'active' => true,
        ]);

        $year->days()->create([
            'date' => now()->startOfDay(),
            'type' => CalendarDay::TYPE_CLASS_COUNCIL,
            'counts_as_school_day' => false,
            'title' => 'Conselho de Classe',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reunião Geral')
            ->assertSee('Conselho de Classe');
    }

    public function test_class_council_is_saved_as_calendar_day_type(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $this->actingAs($admin)
            ->post(route('academic-years.days.store', $year), [
                'date' => '2026-03-10',
                'type' => CalendarDay::TYPE_CLASS_COUNCIL,
                'counts_as_school_day' => '0',
                'title' => 'Conselho de Classe',
                'description' => 'Fechamento do bimestre.',
            ])
            ->assertRedirect(route('academic-years.show', $year));

        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-03-10 00:00:00',
            'type' => CalendarDay::TYPE_CLASS_COUNCIL,
            'counts_as_school_day' => false,
            'title' => 'Conselho de Classe',
        ]);
    }

    public function test_calendar_pdf_data_uses_period_markers_and_description_special_dates(): void
    {
        $year = $this->academicYear([
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-01-31',
        ]);

        $year->periods()->create([
            'name' => 'I Bimestre',
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-23',
            'position' => 1,
        ]);

        $year->days()->create([
            'date' => '2026-01-05',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);

        $year->days()->create([
            'date' => '2026-01-23',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);

        $year->days()->create([
            'date' => '2026-01-15',
            'type' => CalendarDay::TYPE_HOLIDAY,
            'counts_as_school_day' => false,
            'description' => 'Feriado municipal',
        ]);

        $controller = app(\App\Http\Controllers\AcademicCalendarPdfController::class);
        $calendarMethod = new ReflectionMethod($controller, 'calendar');
        $calendarMethod->setAccessible(true);
        $specialDatesMethod = new ReflectionMethod($controller, 'specialDates');
        $specialDatesMethod->setAccessible(true);
        $year->load(['days', 'periods']);

        $calendar = $calendarMethod->invoke($controller, $year);
        $specialDates = $specialDatesMethod->invoke($controller, $year);
        $grid = \App\Support\AcademicCalendarGrid::forAcademicYear($year)->first();
        $gridEntries = $grid['weeks']->flatten(1)->keyBy(fn (array $entry): string => $entry['date']->toDateString());

        $this->assertSame('IP', $calendar[0]['days'][5]['code']);
        $this->assertSame('TP', $calendar[0]['days'][23]['code']);
        $this->assertSame('IP', $gridEntries->get('2026-01-05')['code']);
        $this->assertSame('TP', $gridEntries->get('2026-01-23')['code']);
        $this->assertContains('Feriado municipal - Feriado', array_column($specialDates, 'description'));
    }

    public function test_administrator_can_create_course_component_and_class_inside_academic_year(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $area = KnowledgeArea::query()->where('name', 'Linguagens')->firstOrFail();
        $teacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $year->school_id, 'docente@ctjj.org');

        $this->actingAs($admin)
            ->post(route('academic-years.courses.store', $year), [
                'name' => '3º Ano',
                'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
                'modality' => 'Regular',
                'status' => 'iniciado',
                'workload_hours' => 1000,
                'class_hour_minutes' => 50,
                'active' => '1',
            ])
            ->assertRedirect(route('academic-years.courses.show', [$year, 1]));

        $course = $year->courses()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('academic-years.courses.components.store', [$year, $course]), [
                'name' => 'Língua Portuguesa',
                'knowledge_area_id' => $area->id,
                'weekly_lessons' => '5',
                'workload_hours' => 160,
                'active' => '1',
            ])
            ->assertRedirect(route('academic-years.courses.show', [$year, $course]));

        $this->actingAs($admin)
            ->post(route('academic-years.classes.store', $year), [
                'name' => '3º Ano A',
                'shift' => 'Vespertino',
                'course_ids' => [$course->id],
                'active' => '1',
            ])
            ->assertRedirect(route('academic-years.classes.show', [$year, 1]));

        $course->refresh()->load('components');

        $this->assertDatabaseHas('academic_courses', [
            'academic_year_id' => $year->id,
            'name' => '3º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'class_hour_minutes' => 50,
        ]);
        $this->assertEqualsWithDelta(166.67, $course->calculatedWorkloadHours(), 0.001);
        $this->assertDatabaseHas('curriculum_components', [
            'academic_course_id' => $course->id,
            'name' => 'Língua Portuguesa',
        ]);
        $this->assertDatabaseHas('school_classes', [
            'academic_year_id' => $year->id,
            'name' => '3º Ano A',
        ]);
        $this->assertDatabaseHas('academic_course_school_class', [
            'academic_course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('school_class_components', [
            'curriculum_component_id' => $course->components->first()->id,
            'teacher_person_id' => null,
            'active' => true,
        ]);
    }

    public function test_course_workload_is_recalculated_when_component_is_removed(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'workload_hours' => 200,
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $remainingComponent = $course->components()->create([
            'name' => 'Matemática',
            'weekly_lessons' => 3,
            'active' => true,
        ]);
        $removedComponent = $course->components()->create([
            'name' => 'Física',
            'weekly_lessons' => 2,
            'active' => true,
        ]);
        $course->refreshWorkloadHours();

        $this->assertEqualsWithDelta(166.67, $course->fresh()->calculatedWorkloadHours(), 0.001);

        $this->actingAs($admin)
            ->delete(route('academic-years.courses.components.destroy', [$year, $course, $removedComponent]))
            ->assertRedirect(route('academic-years.courses.show', [$year, $course]));

        $course->refresh();

        $this->assertEqualsWithDelta(100.0, $course->calculatedWorkloadHours(), 0.001);
        $this->assertDatabaseHas('curriculum_components', [
            'id' => $remainingComponent->id,
            'academic_course_id' => $course->id,
        ]);
        $this->assertDatabaseMissing('curriculum_components', [
            'id' => $removedComponent->id,
        ]);
    }

    public function test_class_can_have_non_overlapping_schedule_versions_and_weekly_blocks(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'status' => 'iniciado', 'class_hour_minutes' => 50, 'active' => true]);
        $component = $course->components()->create(['name' => 'Matemática', 'weekly_lessons' => 4, 'active' => true]);
        $class = $year->classes()->create(['name' => '1º Ano A', 'starts_at' => $year->starts_at, 'ends_at' => $year->ends_at, 'active' => true]);
        $class->courses()->attach($course);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $component->id, 'active' => true]);

        $this->actingAs($admin)->post(route('academic-years.classes.schedules.store', [$year, $class]), [
            'name' => 'Horário regular', 'starts_at' => '2026-01-20', 'ends_at' => '2026-06-30',
        ])->assertRedirect();
        $schedule = $class->schedules()->firstOrFail();

        $this->actingAs($admin)->post(route('academic-years.classes.schedules.slots.store', [$year, $class, $schedule]), [
            'weekday' => 1, 'type' => 'aula', 'school_class_component_id' => $assignment->id, 'starts_at' => '06:00', 'ends_at' => '06:50',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('academic-years.classes.schedules.slots.store', [$year, $class, $schedule]), [
            'weekday' => 1, 'type' => 'intervalo', 'starts_at' => '06:50', 'ends_at' => '07:15', 'label' => 'Intervalo',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_class_schedule_slots', ['school_class_schedule_id' => $schedule->id, 'weekday' => 1, 'starts_at' => '06:00', 'ends_at' => '06:50']);
        $this->actingAs($admin)->post(route('academic-years.classes.schedules.store', [$year, $class]), [
            'name' => 'Sobreposto', 'starts_at' => '2026-06-15', 'ends_at' => '2026-07-10',
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_schedule_blocks_respect_school_saturdays_and_weekly_lesson_limit(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'status' => 'iniciado', 'class_hour_minutes' => 50, 'active' => true]);
        $component = $course->components()->create(['name' => 'Filosofia', 'weekly_lessons' => 1, 'active' => true]);
        $class = $year->classes()->create(['name' => '1º Ano A', 'starts_at' => $year->starts_at, 'ends_at' => $year->ends_at, 'active' => true]);
        $class->courses()->attach($course);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $component->id, 'active' => true]);
        $schedule = $class->schedules()->create(['name' => 'Horário regular', 'starts_at' => '2026-01-20']);

        $this->actingAs($admin)->post(route('academic-years.classes.schedules.slots.store', [$year, $class, $schedule]), [
            'weekday' => 6,
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
            'starts_at' => '06:00',
            'ends_at' => '06:40',
        ])->assertSessionHasErrors('weekday');

        $this->actingAs($admin)->post(route('academic-years.classes.schedules.slots.store', [$year, $class, $schedule]), [
            'weekday' => 1,
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
            'starts_at' => '06:00',
            'ends_at' => '06:40',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('academic-years.classes.schedules.slots.store', [$year, $class, $schedule]), [
            'weekday' => 2,
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
            'starts_at' => '06:00',
            'ends_at' => '06:40',
        ])->assertSessionHasErrors('school_class_component_id');
    }

    public function test_schedule_block_can_be_updated_and_schedule_pdfs_create_verifiable_documents(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'status' => 'iniciado', 'class_hour_minutes' => 50, 'active' => true]);
        $component = $course->components()->create(['name' => 'História', 'weekly_lessons' => 2, 'active' => true]);
        $class = $year->classes()->create(['name' => '1º Ano A', 'starts_at' => $year->starts_at, 'ends_at' => $year->ends_at, 'active' => true]);
        $class->courses()->attach($course);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $component->id, 'active' => true]);
        $schedule = $class->schedules()->create(['name' => 'Horário regular', 'starts_at' => '2026-01-20']);
        $slot = $schedule->slots()->create([
            'weekday' => 1,
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
            'starts_at' => '06:00',
            'ends_at' => '06:50',
        ]);

        $this->actingAs($admin)->put(route('academic-years.classes.schedules.slots.update', [$year, $class, $schedule, $slot]), [
            'weekday' => 2,
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
            'starts_at' => '07:00',
            'ends_at' => '07:45',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_class_schedule_slots', [
            'id' => $slot->id,
            'weekday' => 2,
            'starts_at' => '07:00',
            'ends_at' => '07:45',
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.classes.schedules.pdf', [$year, $class, 'schedule' => $schedule->id]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->get(route('academic-years.schedules-pdf', $year))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('issued_documents', ['type' => 'class-schedule', 'school_id' => $year->school_id]);
        $this->assertDatabaseHas('issued_documents', ['type' => 'academic-year-schedules', 'school_id' => $year->school_id]);
    }

    public function test_component_weekly_lessons_must_be_integer(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.courses.components.store', [$year, $course]), [
                'name' => 'Matemática',
                'weekly_lessons' => '2.5',
                'workload_hours' => 120,
                'active' => '1',
            ])
            ->assertSessionHasErrors('weekly_lessons');

        $this->assertDatabaseMissing('curriculum_components', [
            'academic_course_id' => $course->id,
            'name' => 'Matemática',
        ]);
    }

    public function test_component_area_is_inferred_from_curriculum_catalog(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $area = KnowledgeArea::query()
            ->where('name', 'like', '%Natureza%')
            ->where('name', 'like', '%Tecnologias%')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('academic-years.courses.components.store', [$year, $course]), [
                'name' => 'Biologia',
                'weekly_lessons' => '2',
                'workload_hours' => 80,
                'active' => '1',
            ])
            ->assertRedirect(route('academic-years.courses.show', [$year, $course]));

        $this->assertDatabaseHas('curriculum_components', [
            'academic_course_id' => $course->id,
            'name' => 'Biologia',
            'knowledge_area_id' => $area->id,
        ]);
    }

    public function test_course_components_are_grouped_by_area_and_sorted_by_name(): void
    {
        $year = $this->academicYear();
        $languages = KnowledgeArea::query()->where('name', 'Linguagens')->firstOrFail();
        $math = KnowledgeArea::query()->where('name', 'Matemática')->firstOrFail();
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);

        $course->components()->create(['name' => 'Redação', 'knowledge_area_id' => $languages->id, 'workload_hours' => 80, 'active' => true]);
        $course->components()->create(['name' => 'Álgebra', 'knowledge_area_id' => $math->id, 'workload_hours' => 80, 'active' => true]);
        $course->components()->create(['name' => 'Arte', 'knowledge_area_id' => $languages->id, 'workload_hours' => 80, 'active' => true]);

        $groups = $course->fresh()->load('components.area')->componentsGroupedByArea();

        $this->assertSame(['Linguagens', 'Matemática'], $groups->pluck('area')->all());
        $this->assertSame(['Arte', 'Redação'], $groups->first()['components']->pluck('name')->all());
        $this->assertSame(['Álgebra'], $groups->last()['components']->pluck('name')->all());
    }

    public function test_course_components_are_grouped_by_curriculum_formation_for_printing(): void
    {
        $year = $this->academicYear();
        $languages = KnowledgeArea::query()
            ->where('name', 'like', 'Linguagens%')
            ->where('name', 'like', '%Tecnologias')
            ->firstOrFail();
        $itinerary = KnowledgeArea::query()->where('name', 'like', 'Itiner%')->firstOrFail();
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);

        $course->components()->create(['name' => 'Arte', 'knowledge_area_id' => $languages->id, 'workload_hours' => 80, 'active' => true]);
        $course->components()->create(['name' => 'Projeto de Vida', 'knowledge_area_id' => $itinerary->id, 'workload_hours' => 80, 'active' => true]);

        $groups = $course->fresh()->load('components.area')->componentsGroupedByFormationAndArea();

        $this->assertSame(
            [CurriculumCatalog::FORMATION_FGB, CurriculumCatalog::FORMATION_ITINERARY],
            $groups->pluck('formation')->all()
        );
        $this->assertSame(1, $groups->first()['rowspan']);
        $this->assertSame(1, $groups->last()['rowspan']);
    }

    public function test_class_cannot_be_created_for_matrix_without_components(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $course = $year->courses()->create([
            'name' => '3º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.classes.store', $year), [
                'name' => '3º Ano A',
                'course_ids' => [$course->id],
                'active' => '1',
            ])
            ->assertSessionHasErrors('course_ids');

        $this->assertDatabaseMissing('school_classes', [
            'academic_year_id' => $year->id,
            'name' => '3º Ano A',
        ]);
    }

    public function test_class_component_can_receive_temporary_substitute_teacher(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $teacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $year->school_id, 'titular@ctjj.org');
        $substitute = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $year->school_id, 'substituto@ctjj.org');
        $course = $year->courses()->create([
            'name' => 'Ensino Médio',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $component = $course->components()->create([
            'name' => 'Matemática',
            'workload_hours' => 120,
            'active' => true,
        ]);
        $class = $year->classes()->create([
            'name' => '1º Ano A',
            'active' => true,
        ]);
        $class->courses()->attach($course);
        $assignment = $class->componentAssignments()->create([
            'curriculum_component_id' => $component->id,
            'teacher_person_id' => $teacher->person_id,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.classes.components.substitutions.store', [$year, $class, $assignment]), [
                'substitute_teacher_person_id' => $substitute->person_id,
                'starts_at' => '2026-03-01',
                'ends_at' => '2026-03-20',
                'notes' => 'Licença temporária.',
            ])
            ->assertRedirect(route('academic-years.classes.show', [$year, $class]));

        $this->assertDatabaseHas('school_class_component_substitutions', [
            'school_class_component_id' => $assignment->id,
            'substitute_teacher_person_id' => $substitute->person_id,
            'starts_at' => '2026-03-01 00:00:00',
            'ends_at' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_administrator_can_duplicate_course_matrix_with_components(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $area = KnowledgeArea::query()->where('name', 'Linguagens')->firstOrFail();
        $course = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'modality' => 'Regular',
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $course->components()->create([
            'name' => 'Língua Portuguesa',
            'knowledge_area_id' => $area->id,
            'weekly_lessons' => 5,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.courses.duplicate', [$year, $course]))
            ->assertRedirect();

        $copy = $year->courses()
            ->where('name', 'Cópia de 1º Ano')
            ->with('components')
            ->firstOrFail();

        $this->assertSame(AcademicCourse::STAGE_HIGH_SCHOOL, $copy->stage);
        $this->assertSame('Regular', $copy->modality);
        $this->assertSame('planejado', $copy->status);
        $this->assertSame(50, (int) $copy->class_hour_minutes);
        $this->assertCount(1, $copy->components);

        $component = $copy->components->first();
        $this->assertSame('Língua Portuguesa', $component->name);
        $this->assertSame($area->id, $component->knowledge_area_id);
        $this->assertSame(5, $component->weekly_lessons);
        $this->assertEqualsWithDelta(166.67, $copy->calculatedWorkloadHours(), 0.001);
    }

    public function test_enrollment_can_be_transferred_reclassified_and_exported_as_pdf(): void
    {
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.reclassificacao@ctjj.org');
        $courseA = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $courseA->components()->create(['name' => 'Matemática', 'workload_hours' => 120, 'active' => true]);
        $courseB = $year->courses()->create([
            'name' => '2º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $courseB->components()->create(['name' => 'Matemática', 'workload_hours' => 120, 'active' => true]);
        $classA = $year->classes()->create(['name' => '1º Ano A', 'active' => true]);
        $classA->courses()->attach($courseA);
        $classB = $year->classes()->create(['name' => '2º Ano A', 'active' => true]);
        $classB->courses()->attach($courseB);

        $enrollment = $classA->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_by_person_id' => $manager->person_id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($courseA);

        $this->actingAs($manager)
            ->post(route('enrollments.reclassify', $enrollment), [
                'target_school_class_id' => $classB->id,
                'course_ids' => [$courseB->id],
                'reclassified_at' => '2026-04-01',
                'notes' => 'Reclassificação pedagógica.',
            ])
            ->assertRedirect(route('classes.enrollments.index', $classA));

        $newEnrollment = StudentEnrollment::query()
            ->where('school_class_id', $classB->id)
            ->where('person_id', $student->person_id)
            ->firstOrFail();

        $this->assertSame($enrollment->id, $newEnrollment->reclassified_from_enrollment_id);

        $this->actingAs($manager)
            ->patch(route('enrollments.transfer', $newEnrollment), [
                'transferred_at' => '2026-05-01',
                'notes' => 'Transferência externa.',
            ])
            ->assertRedirect(route('classes.enrollments.index', $classB));

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $newEnrollment->id,
            'status' => StudentEnrollment::STATUS_TRANSFERRED,
            'transferred_at' => '2026-05-01 00:00:00',
        ]);

        $this->actingAs($manager)
            ->get(route('enrollments.pdf', $newEnrollment))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-enrollment',
            'person_id' => $student->person_id,
            'school_id' => $year->school_id,
        ]);
    }

    public function test_enrollment_cancellation_preserves_history_and_blocks_new_movements(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.cancelamento@ctjj.org');
        $course = $year->courses()->create([
            'name' => '2º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $schoolClass = $year->classes()->create(['name' => '2º Ano A', 'active' => true]);
        $schoolClass->courses()->attach($course);
        $enrollment = $schoolClass->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_by_person_id' => $administrator->person_id,
            'enrolled_at' => '2026-02-02',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        $this->actingAs($administrator)
            ->patch(route('enrollments.cancel', $enrollment), [
                'cancelled_at' => '2026-03-10',
                'notes' => 'Desistência formalizada pela família.',
            ])
            ->assertRedirect(route('classes.enrollments.index', $schoolClass));

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'status' => StudentEnrollment::STATUS_CANCELLED,
            'cancelled_at' => '2026-03-10 00:00:00',
            'cancelled_by_person_id' => $administrator->person_id,
        ]);

        $this->actingAs($administrator)
            ->patch(route('enrollments.transfer', $enrollment), [
                'transferred_at' => '2026-03-12',
            ])
            ->assertSessionHasErrors('enrollment');
    }

    public function test_management_can_calculate_final_results_for_class_enrollments(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.resultado@ctjj.org');
        $course = $year->courses()->create([
            'name' => '1 Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $schoolClass = $year->classes()->create(['name' => '1 Ano A', 'active' => true]);
        $schoolClass->courses()->attach($course);
        $enrollment = $schoolClass->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_by_person_id' => $administrator->person_id,
            'enrolled_at' => '2026-02-02',
            'status' => StudentEnrollment::STATUS_TRANSFERRED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        $this->actingAs($administrator)
            ->post(route('classes.final-results.calculate', $schoolClass))
            ->assertRedirect(route('classes.enrollments.index', $schoolClass));

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'final_result_status' => StudentEnrollment::FINAL_TRANSFERRED,
            'final_result_calculated_by_person_id' => $administrator->person_id,
        ]);

        $this->actingAs($administrator)
            ->get(route('classes.final-results.pdf', $schoolClass))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'class-final-results',
            'school_id' => $year->school_id,
        ]);
    }

    public function test_management_can_review_closure_and_export_academic_year_final_results(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear(['approved_at' => '2025-12-18']);

        $this->actingAs($administrator)
            ->get(route('academic-years.closure', $year))
            ->assertOk()
            ->assertSee('Conferência de fechamento');

        $this->actingAs($administrator)
            ->get(route('academic-years.final-results.pdf', $year))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'academic-year-final-results',
            'school_id' => $year->school_id,
        ]);
    }

    public function test_management_can_close_academic_year_only_after_consolidation_and_final_results(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear(['approved_at' => '2025-12-18']);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.fechamento@ctjj.org');
        $period = $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-02',
            'ends_at' => '2026-04-10',
            'position' => 1,
        ]);
        $course = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $schoolClass = $year->classes()->create(['name' => '1º Ano A', 'active' => true]);
        $schoolClass->courses()->attach($course);
        $enrollment = $schoolClass->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_by_person_id' => $administrator->person_id,
            'enrolled_at' => '2026-02-02',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        $this->actingAs($administrator)
            ->patch(route('academic-years.close', $year), ['closed_at' => '2026-12-23'])
            ->assertSessionHasErrors('closed_at');

        AcademicPeriodDiaryConsolidation::query()->create([
            'academic_period_id' => $period->id,
            'consolidated' => true,
            'consolidated_at' => now(),
            'consolidated_by_person_id' => $administrator->person_id,
        ]);
        $enrollment->update([
            'final_result_status' => StudentEnrollment::FINAL_APPROVED,
            'final_result_calculated_at' => now(),
            'final_result_calculated_by_person_id' => $administrator->person_id,
        ]);

        $this->actingAs($administrator)
            ->patch(route('academic-years.close', $year), ['closed_at' => '2026-12-23'])
            ->assertRedirect(route('academic-years.show', $year));

        $this->assertDatabaseHas('academic_years', [
            'id' => $year->id,
            'closed_at' => '2026-12-23 00:00:00',
            'closed_by_person_id' => $administrator->person_id,
            'active' => true,
        ]);
    }

    public function test_closed_academic_year_blocks_final_result_recalculation(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear([
            'approved_at' => '2025-12-18',
            'closed_at' => '2026-12-23 12:00:00',
            'closed_by_person_id' => $administrator->person_id,
        ]);
        $course = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $schoolClass = $year->classes()->create(['name' => '1º Ano A', 'active' => true]);
        $schoolClass->courses()->attach($course);

        $this->actingAs($administrator)
            ->post(route('classes.final-results.calculate', $schoolClass))
            ->assertSessionHasErrors('academic_year');
    }

    public function test_student_cannot_have_two_active_enrollments_in_the_same_academic_calendar(): void
    {
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.duplicado@ctjj.org');
        $courseA = $year->courses()->create(['name' => '1º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'status' => 'iniciado', 'active' => true]);
        $courseB = $year->courses()->create(['name' => '2º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'status' => 'iniciado', 'active' => true]);
        $classA = $year->classes()->create(['name' => '1º Ano A', 'active' => true]);
        $classB = $year->classes()->create(['name' => '2º Ano A', 'active' => true]);
        $classA->courses()->attach($courseA);
        $classB->courses()->attach($courseB);
        $classA->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_by_person_id' => $administrator->person_id,
            'enrolled_at' => '2026-02-02',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($administrator)
            ->post(route('classes.enrollments.store', $classB), [
                'person_id' => $student->person_id,
                'course_ids' => [$courseB->id],
                'enrolled_at' => '2026-02-03',
                'type' => StudentEnrollment::TYPE_REGULAR,
            ])
            ->assertSessionHasErrors('person_id');
    }

    public function test_manager_cannot_change_academic_structure_after_year_approval(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestor@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('academic-years.courses.store', $year), [
                'name' => '9º Ano',
                'stage' => AcademicCourse::STAGE_ELEMENTARY,
                'status' => 'iniciado',
                'active' => '1',
            ])
            ->assertSessionHasErrors('approved_at');

        $this->assertDatabaseMissing('academic_courses', [
            'academic_year_id' => $year->id,
            'name' => '9º Ano',
        ]);
    }

    public function test_manager_can_update_academic_year_approval_date(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestor.aprovacao@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'minimum_school_days' => 200,
            'passing_points' => 24,
            'minimum_attendance_percentage' => 75,
            'active' => true,
        ]);
        $originalName = $year->name;

        $this->actingAs($manager)
            ->put(route('academic-years.update', $year), [
                'name' => $year->name,
                'reference_year' => 2026,
                'starts_at' => '2026-01-20',
                'ends_at' => '2026-12-18',
                'approved_at' => '2025-12-12',
                'passing_points' => 24,
                'minimum_attendance_percentage' => 75,
                'active' => '1',
            ])
            ->assertRedirect(route('academic-years.show', $year));

        $this->assertSame('2025-12-12', $year->refresh()->approved_at?->toDateString());
    }

    public function test_manager_cannot_update_sensitive_academic_year_data_after_approval(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestor.sensivel@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'minimum_school_days' => 200,
            'passing_points' => 24,
            'minimum_attendance_percentage' => 75,
            'active' => true,
        ]);
        $originalName = $year->name;

        $this->actingAs($manager)
            ->put(route('academic-years.update', $year), [
                'name' => 'Educação Regular',
                'reference_year' => 2026,
                'starts_at' => '2026-01-20',
                'ends_at' => '2026-12-18',
                'approved_at' => '2025-12-12',
                'passing_points' => 24,
                'minimum_attendance_percentage' => 75,
                'active' => '1',
            ])
            ->assertSessionHasErrors('approved_at');

        $year->refresh();
        $this->assertSame($originalName, $year->name);
        $this->assertSame('2025-12-10', $year->approved_at?->toDateString());
    }

    public function test_manager_can_enroll_student_in_approved_academic_year_class(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestao.matricula@ctjj.org');
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id, 'aluno.matricula@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);
        $course = $year->courses()->create([
            'name' => '3º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $class = $year->classes()->create([
            'name' => '3º Ano A',
            'active' => true,
        ]);
        $class->courses()->attach($course);

        $this->actingAs($manager)
            ->post(route('classes.enrollments.store', $class), [
                'person_id' => $student->person_id,
                'course_ids' => [$course->id],
                'enrolled_at' => '2026-01-20',
                'status' => StudentEnrollment::STATUS_ENROLLED,
                'type' => StudentEnrollment::TYPE_REGULAR,
            ])
            ->assertRedirect(route('classes.enrollments.index', $class));

        $this->assertDatabaseHas('student_enrollments', [
            'school_class_id' => $class->id,
            'person_id' => $student->person_id,
            'status' => StudentEnrollment::STATUS_ENROLLED,
        ]);
        $this->assertDatabaseHas('academic_course_student_enrollment', [
            'academic_course_id' => $course->id,
        ]);
    }

    public function test_enrollment_creates_student_role_with_first_enrollment_and_latest_course_end_dates(): void
    {
        $school = School::query()->create(['name' => 'Escola da Matricula', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school->id, 'gestao.matricula.datas@ctjj.org');
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educacao Basica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);
        $firstPeriod = $year->periods()->create([
            'name' => 'Primeiro semestre',
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-06-30',
            'position' => 1,
        ]);
        $lastPeriod = $year->periods()->create([
            'name' => 'Segundo semestre',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-12-18',
            'position' => 2,
        ]);
        $shortCourse = $year->courses()->create([
            'name' => 'Matriz Semestral',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'ends_period_id' => $firstPeriod->id,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $longCourse = $year->courses()->create([
            'name' => 'Matriz Anual',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'ends_period_id' => $lastPeriod->id,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $class = $year->classes()->create([
            'name' => '1 Ano A',
            'active' => true,
        ]);
        $class->courses()->attach([$shortCourse->id, $longCourse->id]);
        $student = Person::query()->create([
            'full_name' => 'Estudante Sem Vinculo',
            'institutional_email' => 'estudante.sem.vinculo@ctjj.org',
            'cpf' => '12345678901',
            'active' => true,
        ]);

        $this->assertDatabaseMissing('person_school_roles', [
            'person_id' => $student->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
        ]);

        $this->actingAs($manager)
            ->post(route('classes.enrollments.store', $class), [
                'person_id' => $student->id,
                'course_ids' => [$shortCourse->id, $longCourse->id],
                'enrolled_at' => '2026-02-03',
                'type' => StudentEnrollment::TYPE_REGULAR,
            ])
            ->assertRedirect(route('classes.enrollments.index', $class));

        $this->assertDatabaseHas('person_school_roles', [
            'person_id' => $student->id,
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
            'started_at' => '2026-02-03 00:00:00',
            'ended_at' => '2026-12-18 00:00:00',
        ]);
    }

    public function test_manager_only_sees_managed_school_on_enrollment_overview(): void
    {
        $managedSchool = School::query()->create(['name' => 'Escola da Gestão', 'active' => true]);
        $otherSchool = School::query()->create(['name' => 'Outra Escola', 'active' => true]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $managedSchool->id, 'gestao.overview@ctjj.org');

        foreach ([$managedSchool, $otherSchool] as $school) {
            $year = AcademicYear::query()->create([
                'school_id' => $school->id,
                'name' => 'Educação Básica',
                'reference_year' => 2026,
                'starts_at' => '2026-01-20',
                'ends_at' => '2026-12-18',
                'class_hour_minutes' => 50,
                'minimum_school_days' => 200,
                'active' => true,
            ]);
            $course = $year->courses()->create([
                'name' => '1º Ano',
                'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
                'status' => 'iniciado',
                'active' => true,
            ]);
            $class = $year->classes()->create(['name' => '1º Ano A', 'active' => true]);
            $class->courses()->attach($course);
        }

        $this->actingAs($manager)
            ->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('Escola da Gestão')
            ->assertDontSee('Outra Escola');
    }

    public function test_student_cannot_be_enrolled_in_inactive_matrix(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'aluno.matriz.inativa@ctjj.org');
        $course = $year->courses()->create([
            'name' => 'Matriz Inativa',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'active' => false,
        ]);
        $class = $year->classes()->create(['name' => 'Turma A', 'active' => true]);
        $class->courses()->attach($course);

        $this->actingAs($admin)
            ->post(route('classes.enrollments.store', $class), [
                'person_id' => $student->person_id,
                'course_ids' => [$course->id],
                'enrolled_at' => '2026-01-20',
                'status' => StudentEnrollment::STATUS_ENROLLED,
                'type' => StudentEnrollment::TYPE_REGULAR,
            ])
            ->assertSessionHasErrors('course_ids.0');

        $this->assertDatabaseMissing('student_enrollments', [
            'person_id' => $student->person_id,
            'school_class_id' => $class->id,
        ]);
    }

    public function test_student_without_official_identity_cannot_be_enrolled(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $student = Person::query()->create([
            'full_name' => 'Estudante Sem Email',
            'cpf' => '12345678901',
            'active' => true,
        ]);
        $student->schoolRoles()->create([
            'school_id' => $year->school_id,
            'role' => PersonSchoolRole::ROLE_STUDENT,
            'active' => true,
        ]);
        $course = $year->courses()->create([
            'name' => '9º Ano',
            'stage' => AcademicCourse::STAGE_ELEMENTARY,
            'status' => 'iniciado',
            'active' => true,
        ]);
        $class = $year->classes()->create(['name' => '9º Ano A', 'active' => true]);
        $class->courses()->attach($course);

        $this->actingAs($admin)
            ->post(route('classes.enrollments.store', $class), [
                'person_id' => $student->id,
                'course_ids' => [$course->id],
                'enrolled_at' => '2026-01-20',
                'status' => StudentEnrollment::STATUS_ENROLLED,
                'type' => StudentEnrollment::TYPE_REGULAR,
            ])
            ->assertSessionHasErrors('person_id');

        $this->assertDatabaseMissing('student_enrollments', [
            'person_id' => $student->id,
            'school_class_id' => $class->id,
        ]);
    }

    public function test_academic_year_without_periods_can_be_deleted(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $year->days()->create([
            'date' => '2026-01-20',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('academic-years.destroy', $year))
            ->assertRedirect(route('schools.academic-years.index', $year->school_id));

        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
        $this->assertDatabaseMissing('calendar_days', ['academic_year_id' => $year->id]);
    }

    public function test_academic_year_with_periods_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-30',
            'position' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('academic-years.destroy', $year))
            ->assertRedirect(route('schools.academic-years.index', $year->school_id));

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_academic_calendar_pdf_creates_verifiable_document(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();

        $year->days()->create([
            'date' => '2026-01-20',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
        $year->periods()->create([
            'name' => '1º Bimestre',
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-04-30',
            'position' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.calendar-pdf', $year))
            ->assertOk()
            ->assertHeader('content-disposition');

        $document = IssuedDocument::query()
            ->where('type', 'academic-calendar')
            ->firstOrFail();

        $this->assertSame($year->school_id, $document->school_id);
        $this->assertSame($year->id, $document->payload['academic_year_id']);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    public function test_academic_matrices_pdf_creates_verifiable_document(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $area = KnowledgeArea::query()->where('name', 'Linguagens')->firstOrFail();
        $course = $year->courses()->create([
            'name' => '9º Ano',
            'stage' => AcademicCourse::STAGE_ELEMENTARY,
            'status' => 'iniciado',
            'class_hour_minutes' => 60,
            'active' => true,
        ]);
        $course->components()->create([
            'knowledge_area_id' => $area->id,
            'name' => 'Língua Portuguesa',
            'weekly_lessons' => 6,
            'workload_hours' => 240,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.matrices-pdf', $year))
            ->assertOk()
            ->assertHeader('content-disposition');

        $document = IssuedDocument::query()
            ->where('type', 'academic-matrices')
            ->firstOrFail();

        $this->assertSame($year->school_id, $document->school_id);
        $this->assertSame($year->id, $document->payload['academic_year_id']);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    public function test_single_academic_matrix_pdf_creates_verifiable_document(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $year = $this->academicYear();
        $area = KnowledgeArea::query()->where('name', 'Linguagens')->firstOrFail();
        $course = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $course->components()->create([
            'knowledge_area_id' => $area->id,
            'name' => 'Língua Portuguesa',
            'weekly_lessons' => 5,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.courses.matrix-pdf', [$year, $course]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $document = IssuedDocument::query()
            ->where('type', 'academic-matrices')
            ->firstOrFail();

        $this->assertSame($year->school_id, $document->school_id);
        $this->assertSame(1, $document->payload['rows_count']);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    private function academicYear(array $overrides = []): AcademicYear
    {
        $school = School::query()->create($this->officialSchoolData(['name' => 'Escola A']));

        return AcademicYear::query()->create(array_merge([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function officialSchoolData(array $overrides = []): array
    {
        static $sequence = 0;

        $sequence++;

        return array_merge([
            'name' => 'Escola Oficial '.$sequence,
            'legal_name' => 'Centro Tecnico Juvenil de Jarudore',
            'cnpj' => str_pad((string) $sequence, 14, '0', STR_PAD_LEFT),
            'inep' => str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'founded_at' => '1990-10-04',
            'phone' => '(66) 99613-6796',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'email' => 'ctjj.mt@gmail.com',
            'website' => 'https://ctjj.org',
            'letterhead_text' => 'Credenciamento e autorizacao vigentes.',
            'address' => 'Av. Sao Joao',
            'district' => 'Jarudore',
            'number' => 's/n',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-970',
            'active' => true,
        ], $overrides);
    }

    private function userWithRole(string $role, ?int $schoolId = null, string $email = 'usuario@ctjj.org'): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => $email,
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria da Silva',
            'father_name' => 'José da Silva',
            'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }
}
