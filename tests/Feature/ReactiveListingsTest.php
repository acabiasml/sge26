<?php

namespace Tests\Feature;

use App\Livewire\ScreenListing;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ReactiveListingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = PersonSchoolRole::ROLE_ADMINISTRATOR, ?School $school = null): User
    {
        $person = Person::create(['full_name' => 'Operador de teste']);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $school?->id, 'active' => true]);
        $user = User::factory()->create(['person_id' => $person->id]);
        $this->actingAs($user);

        return $user;
    }

    private function student(string $name, School $school): Person
    {
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano de teste', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'Turma de teste']);
        $student = Person::create(['full_name' => $name]);
        StudentEnrollment::create(['person_id' => $student->id, 'school_class_id' => $class->id, 'enrolled_at' => '2026-01-01', 'status' => StudentEnrollment::STATUS_ENROLLED]);

        return $student;
    }

    public function test_history_search_updates_and_resets_pagination(): void
    {
        $this->user();
        $school = School::create(['name' => 'Escola Alfa', 'active' => true]);
        foreach (range(1, 11) as $number) {
            $this->student(sprintf('Aluno %02d', $number), $school);
        }
        Livewire::test(ScreenListing::class, ['screen' => 'histories'])
            ->assertSee('Aluno 01')->assertDontSee('Aluno 11')
            ->call('gotoPage', 2)->assertSee('Aluno 11')->assertDontSee('Aluno 01')
            ->set('filters.q', 'Aluno 02')->assertSet('paginators.page', 1)
            ->assertSee('Aluno 02')->assertDontSee('Aluno 01')
            ->set('filters.q', 'Inexistente')->assertSee(__('screens.no_student'))
            ->call('clearFilters')->assertSee('Aluno 01');
    }

    public function test_history_updates_keep_school_permissions(): void
    {
        $school = School::create(['name' => 'Escola Permitida', 'active' => true]);
        $other = School::create(['name' => 'Escola Restrita', 'active' => true]);
        $this->student('Estudante Visível', $school);
        $this->student('Estudante Restrito', $other);
        $this->user(PersonSchoolRole::ROLE_MANAGER, $school);
        Livewire::test(ScreenListing::class, ['screen' => 'histories'])
            ->assertSee('Estudante Visível')->assertDontSee('Estudante Restrito')
            ->set('filters.q', 'Restrito')->assertDontSee('Estudante Restrito')
            ->set('filters.school', $other->id)->assertDontSee('Estudante Restrito');
    }

    public function test_collection_search_and_pagination_work_without_javascript_pagination(): void
    {
        $this->user();
        foreach (range(1, 11) as $number) {
            KnowledgeArea::create(['name' => sprintf('Área %02d', $number), 'formation' => 'Formação Geral Básica']);
        }
        Livewire::test(ScreenListing::class, ['screen' => 'knowledge-areas'])
            ->assertSee('Área 01')->assertDontSee('Área 11')
            ->call('gotoPage', 2)->assertSee('Área 11')
            ->set('filters.q', 'Área 03')->assertSet('paginators.page', 1)
            ->assertSee('Área 03')->assertDontSee('Área 11');
    }

    public function test_announcements_are_searched_before_pagination(): void
    {
        $this->user();
        foreach (range(1, 12) as $number) {
            Announcement::create(['title' => 'Aviso '.$number, 'body' => 'Mensagem', 'starts_at' => now()->subDays($number), 'active' => true]);
        }
        Livewire::test(ScreenListing::class, ['screen' => 'announcements'])
            ->set('filters.q', 'Aviso 12')->assertSee('Aviso 12')->assertDontSee('Aviso 2');
    }

    public function test_context_is_locked_and_cannot_switch_to_another_school(): void
    {
        $school = School::create(['name' => 'Escola Permitida']);
        $other = School::create(['name' => 'Escola Restrita']);
        $this->user(PersonSchoolRole::ROLE_MANAGER, $school);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(ScreenListing::class, ['screen' => 'school-years', 'context' => ['school' => $school->id]])
            ->set('context.school', $other->id);
    }

    public function test_authorization_is_rechecked_on_every_update(): void
    {
        $school = School::create(['name' => 'Escola Permitida']);
        $user = $this->user(PersonSchoolRole::ROLE_MANAGER, $school);
        $component = Livewire::test(ScreenListing::class, ['screen' => 'school-years', 'context' => ['school' => $school->id]]);
        $user->person->schoolRoles()->delete();
        $user->unsetRelations();
        $component->set('filters.q', 'Ano')->assertForbidden();
    }

    public function test_invalid_filter_shapes_are_rejected(): void
    {
        $this->user();
        Livewire::test(ScreenListing::class, ['screen' => 'histories'])
            ->set('filters.q', ['invalid'])->assertStatus(422);
    }

    public function test_document_visibility_cannot_be_expanded_by_a_manager(): void
    {
        $school = School::create(['name' => 'Escola Permitida']);
        $this->user(PersonSchoolRole::ROLE_MANAGER, $school);
        Livewire::test(ScreenListing::class, ['screen' => 'documents'])
            ->call('selectFilter', 'visibility', 'all')->assertForbidden();
    }
}
