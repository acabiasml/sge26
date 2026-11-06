<?php

namespace Tests\Feature;

use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryCurriculumCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administration_can_see_menu_or_manage_the_catalog_and_it_is_paginated(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin)->get(route('history-curriculum.index'))->assertOk()
            ->assertSee('Áreas e componentes')->assertSee('Catálogo de componentes')
            ->assertViewHas('components', fn ($items) => $items->perPage() === 10 && $items->hasMorePages());
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

    private function userWithRole(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa '.$role, 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
