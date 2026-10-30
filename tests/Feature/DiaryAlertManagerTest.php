<?php

namespace Tests\Feature;

use App\Livewire\DiaryAlertManager;
use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\DiaryAlert;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class DiaryAlertManagerTest extends TestCase
{
    use RefreshDatabase;

    private function user(School $school, string $role = PersonSchoolRole::ROLE_MANAGER): User
    {
        $person = Person::create([
            'full_name' => 'Pessoa '.User::count(), 'cpf' => fake()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT',
            'nationality' => 'Brasileira', 'mother_name' => 'Responsável', 'address' => 'Rua de teste',
            'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now(),
        ]);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Escola de teste', 'active' => true]);
        $author = $this->user($school);
        $teacher = $this->user($school, PersonSchoolRole::ROLE_TEACHER);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano de teste', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'approved_at' => now(), 'active' => true]);
        $course = $year->courses()->create(['name' => 'Matriz de teste', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL, 'active' => true]);
        $component = $course->components()->create(['name' => 'Matemática', 'active' => true]);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'Turma A', 'active' => true]);
        $class->courses()->attach($course);
        SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'teacher_person_id' => $teacher->person_id, 'active' => true]);
        $period = AcademicPeriod::create(['academic_year_id' => $year->id, 'name' => '1º Bimestre', 'starts_at' => '2026-01-01', 'ends_at' => '2026-03-31', 'position' => 1]);
        $this->actingAs($author);

        return compact('school', 'author', 'teacher', 'year', 'course', 'component', 'class', 'period');
    }

    private function alert(array $f, array $overrides = []): DiaryAlert
    {
        return DiaryAlert::create(array_replace([
            'school_class_id' => $f['class']->id, 'curriculum_component_id' => $f['component']->id,
            'academic_period_id' => $f['period']->id, 'from_person_id' => $f['author']->person_id,
            'to_person_id' => $f['teacher']->person_id, 'message' => 'Mensagem original',
        ], $overrides));
    }

    private function modal(array $f)
    {
        return Livewire::test(DiaryAlertManager::class)->call('open', $f['class']->id, $f['component']->id, $f['period']->id);
    }

    public function test_modal_lists_only_alerts_of_the_selected_diary_and_period(): void
    {
        $f = $this->fixture();
        $this->alert($f, ['message' => 'Alerta desta seleção']);
        $period = $f['year']->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-01', 'ends_at' => '2026-06-30', 'position' => 2]);
        $this->alert($f, ['academic_period_id' => $period->id, 'message' => 'Outro período']);
        $component = $f['course']->components()->create(['name' => 'História', 'active' => true]);
        $this->alert($f, ['curriculum_component_id' => $component->id, 'message' => 'Outro componente']);
        $class = $f['year']->classes()->create(['name' => 'Turma B']);
        $this->alert($f, ['school_class_id' => $class->id, 'message' => 'Outra turma']);
        $this->modal($f)->assertSee('Alerta desta seleção')->assertDontSee('Outro período')->assertDontSee('Outro componente')->assertDontSee('Outra turma')->assertDispatched('diary-alert-manager-open');
    }

    public function test_create_and_edit_preserve_recipient_author_and_read_status(): void
    {
        $f = $this->fixture();
        $modal = $this->modal($f)->set('message', '  Confira a frequência.  ')->call('save')->assertHasNoErrors()->assertDispatched('diary-alerts-changed');
        $alert = DiaryAlert::firstOrFail();
        $this->assertSame('Confira a frequência.', $alert->message);
        $this->assertSame($f['author']->person_id, $alert->from_person_id);
        $this->assertSame($f['teacher']->person_id, $alert->to_person_id);
        $alert->update(['dismissed_at' => now()]);
        $seen = $alert->fresh()->dismissed_at->toDateTimeString();
        $modal->call('edit', $alert->id)->assertSet('message', 'Confira a frequência.')
            ->set('message', '<script>conteúdo literal</script>')->call('save')->assertHasNoErrors()
            ->assertSee('<script>conteúdo literal</script>')->assertDontSee('<script>conteúdo literal</script>', false);
        $this->assertSame('<script>conteúdo literal</script>', $alert->fresh()->message);
        $this->assertSame($seen, $alert->fresh()->dismissed_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => DiaryAlert::class, 'auditable_id' => $alert->id, 'action' => 'updated']);
    }

    public function test_delete_requires_confirmation_and_can_be_cancelled(): void
    {
        $f = $this->fixture();
        $alert = $this->alert($f);
        $modal = $this->modal($f)->call('requestDelete', $alert->id);
        $this->assertDatabaseHas('diary_alerts', ['id' => $alert->id]);
        $modal->call('cancelDelete')->assertSet('deletingAlertId', null)->call('requestDelete', $alert->id)->call('delete')->assertDispatched('diary-alerts-changed');
        $this->assertDatabaseMissing('diary_alerts', ['id' => $alert->id]);
    }

    public function test_other_authors_and_other_schools_cannot_edit_or_delete_alerts(): void
    {
        $f = $this->fixture();
        $other = $this->user($f['school']);
        $alert = $this->alert($f, ['from_person_id' => $other->person_id]);
        $this->modal($f)->call('edit', $alert->id)->assertForbidden();
        $this->modal($f)->call('requestDelete', $alert->id)->assertForbidden();
        $this->delete(route('teacher-diaries.alerts.destroy', $alert))->assertForbidden();
        $otherSchool = School::create(['name' => 'Escola restrita']);
        $this->actingAs($this->user($otherSchool));
        Livewire::test(DiaryAlertManager::class)->call('open', $f['class']->id, $f['component']->id, $f['period']->id)->assertForbidden();
        $this->actingAs($f['teacher']);
        Livewire::test(DiaryAlertManager::class)->call('open', $f['class']->id, $f['component']->id, $f['period']->id)->assertForbidden();
    }

    public function test_alert_id_from_another_period_cannot_be_used_even_by_its_author(): void
    {
        $f = $this->fixture();
        $period = $f['year']->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-01', 'ends_at' => '2026-06-30', 'position' => 2]);
        $alert = $this->alert($f, ['academic_period_id' => $period->id]);
        $this->modal($f)->call('edit', $alert->id)->assertNotFound();
        $this->modal($f)->call('requestDelete', $alert->id)->assertNotFound();
    }

    public function test_modal_scope_is_locked_against_client_changes(): void
    {
        $f = $this->fixture();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->modal($f)->set('periodId', 999);
    }

    public function test_blank_and_long_messages_do_not_create_alerts(): void
    {
        $f = $this->fixture();
        $this->modal($f)->set('message', '   ')->call('save')->assertHasErrors(['message' => 'required'])
            ->set('message', str_repeat('a', 2001))->call('save')->assertHasErrors(['message' => 'max']);
        $this->assertDatabaseCount('diary_alerts', 0);
    }
}
