<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumComponentsTable;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryCurriculumCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administration_can_see_menu_or_manage_the_catalog_and_it_is_paginated(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin)->get(route('history-curriculum.index'))->assertOk()
            ->assertSee('Áreas e componentes')->assertSee('Catálogo de componentes')
            ->assertSeeLivewire(HistoryCurriculumComponentsTable::class);
        Livewire::test(HistoryCurriculumComponentsTable::class)->assertSet('perPage', 10)->assertSet('paginationCurrentCount', 10);
        $this->get(route('dashboard'))->assertOk()->assertSee(route('history-curriculum.index'));
        $entry = HistoryCurriculumComponent::firstOrFail();
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('dashboard'))->assertOk()->assertDontSee(route('history-curriculum.index'));
            $this->get(route('history-curriculum.index'))->assertForbidden();
            $this->post(route('history-curriculum.store'), [])->assertForbidden();
            $this->put(route('history-curriculum.update', $entry), [])->assertForbidden();
            $this->delete(route('history-curriculum.destroy', $entry))->assertForbidden();
        }
    }

    public function test_administrator_can_create_edit_deactivate_and_remove_and_stage_is_validated(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $data = ['name' => 'Expressão Corporal', 'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'knowledge_area_id' => $area->id, 'active' => 1];
        $this->post(route('history-curriculum.store'), $data)->assertSessionHasNoErrors();
        $item = HistoryCurriculumComponent::where('name', 'Expressão Corporal')->sole();
        $this->post(route('history-curriculum.store'), $data)->assertSessionHasErrors('name');
        $data['active'] = 0;
        $this->put(route('history-curriculum.update', $item), $data)->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->active);
        $data['knowledge_area_id'] = KnowledgeArea::where('name', 'Linguagens e suas Tecnologias')->value('id');
        $this->put(route('history-curriculum.update', $item), $data)->assertSessionHasErrors('knowledge_area_id');
        $this->delete(route('history-curriculum.destroy', $item))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $item->id]);
    }

    public function test_catalog_associations_prevent_incompatible_area_renaming_and_deletion(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $this->put(route('knowledge-areas.update', $area), ['name' => 'Ciências da Natureza e suas Tecnologias', 'formation' => 'Formação Geral Básica'])->assertSessionHasErrors('name');
        $this->assertSame('Linguagens', $area->fresh()->name);
        $this->delete(route('knowledge-areas.destroy', $area))->assertSessionHasErrors('area');
        $this->assertDatabaseHas('knowledge_areas', ['id' => $area->id]);
    }

    public function test_framework_table_searches_filters_sorts_and_paginates_catalog_entries(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        HistoryCurriculumComponent::query()->delete();
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $catalogIds = [];
        foreach (range(1, 11) as $number) {
            $catalogIds[] = HistoryCurriculumComponent::create(['name' => sprintf('Disciplina %02d', $number), 'knowledge_area_id' => $area->id, 'education_stage' => 'fundamental', 'formation' => 'Formação Geral Básica', 'active' => $number !== 11])->id;
        }
        $mediumArea = KnowledgeArea::where('name', 'Matemática e suas Tecnologias')->firstOrFail();
        $medium = HistoryCurriculumComponent::create(['name' => 'Zeta Médio', 'knowledge_area_id' => $mediumArea->id, 'education_stage' => 'medio', 'formation' => 'Formação Geral Básica', 'active' => true]);
        $table = Livewire::test(HistoryCurriculumComponentsTable::class)
            ->assertSee('Disciplina 01')->assertDontSee('Disciplina 11')
            ->assertSet('paginationCurrentCount', 10)
            ->call('gotoPage', 2)->assertSee('Disciplina 11')->assertSee('Zeta Médio')->assertDontSee('Disciplina 01')
            ->set('search', 'Disciplina 03')->assertSet('paginators.page', 1)
            ->assertSee('Disciplina 03')->assertDontSee('Disciplina 02')
            ->set('search', 'Tecnologias')->assertSee('Zeta Médio')->assertDontSee('Disciplina 03')
            ->set('search', '')
            ->call('setFilter', 'education_stage', 'medio')->assertSee('Zeta Médio')->assertDontSee('Disciplina 01')
            ->call('setFilter', 'education_stage', '')
            ->call('setFilter', 'status', '0')->assertSee('Disciplina 11')->assertDontSee('Disciplina 01')->assertDontSee('Zeta Médio')
            ->call('setFilter', 'status', '')
            ->call('sortBy', 'name');
        $this->assertSame([$medium->id, $catalogIds[10], $catalogIds[9]], array_slice($table->get('paginationCurrentItems'), 0, 3));
    }

    public function test_table_rechecks_administrator_access_on_reactive_requests(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $table = Livewire::test(HistoryCurriculumComponentsTable::class);
        $admin->person->schoolRoles()->delete();
        $admin->unsetRelations();
        $table->set('search', 'Matemática')->assertForbidden();
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_TEACHER));
        Livewire::test(HistoryCurriculumComponentsTable::class)->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa '.$role, 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
