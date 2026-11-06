<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatrixManagedComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $stage = 'tecnico', string $role = PersonSchoolRole::ROLE_MANAGER): AcademicCourse
    {
        $school = School::create(['name' => 'Escola de teste']);
        $person = Person::create(['full_name' => 'Responsável pela matriz', 'cpf' => '12345678901', 'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria',
            'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $school->id, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $person->id]));
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);

        return $year->courses()->create(['name' => 'Matriz de teste', 'stage' => $stage, 'active' => true]);
    }

    private function entry(string $name, string $stage = 'tecnico', bool $active = true, ?KnowledgeArea $area = null): HistoryCurriculumComponent
    {
        $area ??= KnowledgeArea::firstOrCreate(['name' => 'Área Gerenciada'], ['formation' => 'Formação Técnica Profissional', 'active' => true]);

        return HistoryCurriculumComponent::create(['name' => $name, 'knowledge_area_id' => $area->id, 'education_stage' => $stage,
            'formation' => $stage === 'tecnico' ? 'Formação Técnica Profissional' : 'Formação Geral Básica', 'active' => $active]);
    }

    private function names($response, string $id): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="name"] | //datalist')->length);
        $options = $xpath->query('//select[@id="'.$id.'" and @name="name" and @required]/option[@value!=""]');

        return array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options));
    }

    public function test_create_and_edit_offer_only_managed_active_stage_compatible_names_in_alphabetical_order(): void
    {
        $course = $this->course();
        $zeta = $this->entry('Zoologia');
        $this->entry('Álgebra');
        $this->entry('Biologia');
        $this->entry('Apenas no médio', 'medio');
        $this->entry('Componente inativo', active: false);
        $inactiveArea = KnowledgeArea::create(['name' => 'Área inativa', 'formation' => 'Formação Técnica Profissional', 'active' => false]);
        $this->entry('Com área inativa', area: $inactiveArea);
        $this->entry('Biologia', area: KnowledgeArea::create(['name' => 'Outra área', 'formation' => 'Formação Técnica Profissional', 'active' => true]));
        $component = $course->components()->create(['name' => $zeta->name, 'knowledge_area_id' => $zeta->knowledge_area_id, 'workload_hours' => 80]);
        $before = [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()];
        $create = $this->get(route('academic-years.courses.show', [$course->academicYear, $course]))->assertOk();
        $edit = $this->get(route('academic-years.courses.components.show', [$course->academicYear, $course, $component]))->assertOk();
        $this->assertSame(['Álgebra', 'Biologia', 'Zoologia'], $this->names($create, 'new_component_name'));
        $this->assertSame(['Álgebra', 'Biologia', 'Zoologia'], $this->names($edit, 'component_name'));
        $this->assertSame($before, [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()]);
        $create->assertDontSee('Gerenciar catálogo');
    }

    public function test_management_can_save_catalog_names_but_cannot_create_or_rename_with_free_text(): void
    {
        $course = $this->course();
        $entry = $this->entry('Componente cadastrado');
        $other = $this->entry('Outro componente cadastrado');
        $payload = ['name' => $entry->name, 'knowledge_area_id' => $entry->knowledge_area_id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80];
        $store = route('academic-years.courses.components.store', [$course->academicYear, $course]);
        $this->post($store, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $component = $course->components()->sole();
        $update = route('academic-years.courses.components.update', [$course->academicYear, $course, $component]);
        $this->put($update, array_replace($payload, ['name' => $other->name]))->assertSessionHasNoErrors();
        $before = [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()];
        foreach (['Nome inventado', 'Nome <script>alert(1)</script>', ''] as $name) {
            $invalid = array_replace($payload, ['name' => $name]);
            $this->post($store, $invalid)->assertSessionHasErrors('name');
            $this->put($update, $invalid)->assertSessionHasErrors('name');
        }
        $this->assertSame(1, $course->components()->count());
        $this->assertSame($before, [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()]);
    }

    public function test_server_rechecks_catalog_changes_and_stage_even_for_administrators(): void
    {
        $course = $this->course('fundamental', PersonSchoolRole::ROLE_ADMINISTRATOR);
        $entry = $this->entry('Componente disponível', 'fundamental');
        $store = route('academic-years.courses.components.store', [$course->academicYear, $course]);
        $this->get(route('academic-years.courses.show', [$course->academicYear, $course]))->assertOk()->assertSee($entry->name);
        $entry->update(['active' => false]);
        $otherStage = $this->entry('Somente no médio', 'medio');
        $wrongArea = KnowledgeArea::where('name', 'Linguagens e suas Tecnologias')->firstOrFail();
        $incompatible = $this->entry('Área incompatível', 'fundamental', area: $wrongArea);
        $deleted = $this->entry('Excluído após abrir a tela', 'fundamental');
        $deleted->delete();
        foreach ([$entry, $otherStage, $incompatible, $deleted] as $unavailable) {
            $this->post($store, ['name' => $unavailable->name, 'workload_mode' => 'workload_hours', 'workload_hours' => 80])->assertSessionHasErrors('name');
        }
        HistoryCurriculumComponent::where('education_stage', 'fundamental')->update(['active' => false]);
        $page = $this->get(route('academic-years.courses.show', [$course->academicYear, $course]))->assertOk()
            ->assertSee('Não há componentes ativos no catálogo para esta etapa de ensino.')
            ->assertSee(route('history-curriculum.index'));
        $this->assertSame([], $this->names($page, 'new_component_name'));
        $this->assertSame(0, $course->components()->count());
    }

    public function test_an_inactive_current_selection_can_be_retained_without_becoming_available_in_new_matrices(): void
    {
        $course = $this->course();
        $entry = $this->entry('Componente já utilizado');
        $component = $course->components()->create(['name' => $entry->name, 'knowledge_area_id' => $entry->knowledge_area_id, 'workload_hours' => 80]);
        $entry->area->update(['active' => false]);
        $edit = $this->get(route('academic-years.courses.components.show', [$course->academicYear, $course, $component]))->assertOk();
        $this->assertSame([$entry->name], $this->names($edit, 'component_name'));
        $payload = ['name' => $entry->name, 'knowledge_area_id' => $entry->knowledge_area_id, 'workload_mode' => 'workload_hours', 'workload_hours' => 120];
        $this->post(route('academic-years.courses.components.store', [$course->academicYear, $course]), $payload)->assertSessionHasErrors('name');
        $this->put(route('academic-years.courses.components.update', [$course->academicYear, $course, $component]), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(120, $component->fresh()->workload_hours);
        $this->assertSame($entry->name, $component->fresh()->name);
    }
}
