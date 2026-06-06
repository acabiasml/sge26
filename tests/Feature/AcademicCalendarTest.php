<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CalendarDay;
use App\Models\CalendarEvent;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_academic_year(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'school_id' => $school->id,
                'name' => 'Educação Básica',
                'reference_year' => 2026,
                'starts_at' => '2026-01-20',
                'ends_at' => '2026-12-18',
                'approved_at' => '2025-12-10',
                'class_hour_minutes' => 50,
                'minimum_school_days' => 200,
                'active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', [
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'class_hour_minutes' => 50,
        ]);
    }

    public function test_academic_year_creation_generates_initial_calendar_days(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'school_id' => $school->id,
                'name' => 'Educação Básica',
                'reference_year' => 2026,
                'starts_at' => '2026-01-05',
                'ends_at' => '2026-01-11',
                'class_hour_minutes' => 50,
                'minimum_school_days' => 200,
                'active' => '1',
                'ignore_weekends' => '1',
                'recesses' => [
                    [
                        'title' => 'Recesso inicial',
                        'starts_at' => '2026-01-07',
                        'ends_at' => '2026-01-07',
                    ],
                ],
            ])
            ->assertRedirect();

        $year = AcademicYear::query()->firstOrFail();

        $this->assertSame(7, $year->days()->count());
        $this->assertSame(4, $year->days()->where('counts_as_school_day', true)->count());
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-07',
            'type' => CalendarDay::TYPE_RECESS,
            'counts_as_school_day' => false,
            'title' => 'Recesso inicial',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'academic_year_id' => $year->id,
            'date' => '2026-01-10',
            'type' => CalendarDay::TYPE_WEEKEND,
            'counts_as_school_day' => false,
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

    public function test_dashboard_shows_announcements_and_upcoming_events(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id, 'estudante@ctjj.org');

        Announcement::query()->create([
            'school_id' => $school->id,
            'title' => 'Reunião Geral',
            'body' => 'Atenção ao recado.',
            'starts_at' => now()->subHour(),
            'active' => true,
        ]);

        CalendarEvent::query()->create([
            'school_id' => $school->id,
            'title' => 'Festa Junina',
            'starts_at' => now()->addDays(2),
            'all_day' => true,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reunião Geral')
            ->assertSee('Festa Junina');
    }

    public function test_academic_year_without_periods_or_events_can_be_deleted(): void
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
            ->assertRedirect(route('academic-years.index'));

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
            ->assertRedirect(route('academic-years.index'));

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

    private function academicYear(): AcademicYear
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        return AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);
    }

    private function userWithRole(string $role, ?int $schoolId = null, string $email = 'usuario@ctjj.org'): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => $email,
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
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
