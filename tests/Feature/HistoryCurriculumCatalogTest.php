<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumComponentsTable;
use App\Models\AcademicYear;
use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            $this->delete(route('knowledge-areas.destroy', $entry->area))->assertForbidden();
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

    public function test_catalog_associations_prevent_incompatible_renaming_but_allow_removing_an_unused_area(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $this->put(route('knowledge-areas.update', $area), ['name' => 'Ciências da Natureza e suas Tecnologias', 'formation' => 'Formação Geral Básica'])->assertSessionHasErrors('name');
        $this->assertSame('Linguagens', $area->fresh()->name);
        $this->delete(route('knowledge-areas.destroy', $area))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('knowledge_areas', ['id' => $area->id]);
        $this->assertDatabaseMissing('history_curriculum_components', ['knowledge_area_id' => $area->id]);
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

    public function test_matrix_modal_lists_all_linked_matrices_even_with_zero_history_usages(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $school = School::create(['name' => 'Escola dos vínculos']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano 2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $courses = collect(['Matriz A', 'Matriz B'])->map(fn ($name) => $year->courses()->create(['name' => $name, 'stage' => 'fundamental']));
        foreach ($courses as $course) {
            $matrix = $course->components()->create(['name' => 'Arte dos vínculos', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id'), 'active' => true]);
        }
        $entry = $matrix->historyCatalogEntries()->sole();
        $this->assertSame(0, $entry->historyComponents()->count());
        $table = Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Arte dos vínculos');
        $table->assertSeeHtml('data-target="#catalog-links-'.$entry->id.'"')
            ->assertSeeHtml('id="catalog-links-'.$entry->id.'"')
            ->assertSee('A contagem de históricos não inclui os vínculos com matrizes.')
            ->assertSee('Escola dos Vínculos')->assertSee('Ano 2026')
            ->assertDontSeeHtml('action="'.route('history-curriculum.destroy', $entry).'"');
        foreach ($courses as $course) {
            $table->assertSeeHtml('href="'.route('academic-years.courses.show', [$year, $course]).'"');
            $this->get(route('academic-years.courses.show', [$year, $course]))->assertOk();
        }
        $this->delete(route('history-curriculum.destroy', $entry))->assertForbidden();
        $this->assertSame(2, $entry->matrixComponents()->count());
        // O modal deve continuar disponível depois da paginação reativa.
        $table->set('search', '')->call('gotoPage', 2)->set('search', 'Arte dos vínculos')
            ->assertSeeHtml('id="catalog-links-'.$entry->id.'"');
    }

    public function test_unused_catalog_entry_can_be_removed_after_its_matrix_link_is_removed(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $school = School::create(['name' => 'Escola de origem']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz original', 'stage' => 'fundamental']);
        $matrix = $course->components()->create(['name' => 'Componente original', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id')]);
        $entry = $matrix->historyCatalogEntries()->sole();
        $this->assertTrue($entry->matrix_origin);
        $this->delete(route('history-curriculum.destroy', $entry))->assertForbidden();
        // Renomear na matriz deixa o nome antigo sem vínculos efetivos.
        $matrix->update(['name' => 'Componente atual']);
        $snapshot = $matrix->fresh()->getAttributes();
        $current = $matrix->historyCatalogEntries()->sole();
        $this->assertSame(0, $entry->matrixComponents()->count());
        Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Componente original')
            ->assertSeeHtml('action="'.route('history-curriculum.destroy', $entry).'"')
            ->assertSee('Excluir este componente do catálogo? Ele não está vinculado a matrizes nem a históricos.');
        $this->delete(route('history-curriculum.destroy', $entry))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $entry->id]);
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $current->id]);
        $this->assertSame($snapshot, $matrix->fresh()->getAttributes());
    }

    public function test_history_links_block_deletion_even_when_there_is_no_matrix_link(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $entry = HistoryCurriculumComponent::create(['name' => 'Componente em histórico', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id'), 'education_stage' => 'fundamental', 'formation' => 'Formação Geral Básica', 'active' => true]);
        $student = Person::create(['full_name' => 'Estudante de teste']);
        $school = School::create(['name' => 'Escola do histórico']);
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico de teste', 'education_stage' => 'fundamental']);
        $component = $history->components()->create(['name' => $entry->name, 'formation' => $entry->formation, 'knowledge_area' => $entry->area->name]);
        $entry->historyComponents()->attach($component);
        foreach ([false, true] as $matrixOrigin) {
            $entry->forceFill(['matrix_origin' => $matrixOrigin])->save();
            Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Componente em histórico')
                ->assertDontSeeHtml('aria-label="Remover componente: '.$entry->name.'"');
            $this->delete(route('history-curriculum.destroy', $entry))->assertSessionHasErrors('catalog_component');
            $this->assertDatabaseHas('history_catalog_usages', ['history_curriculum_component_id' => $entry->id, 'student_academic_history_component_id' => $component->id]);
        }
    }

    public function test_zero_use_area_can_be_deleted_with_unused_catalog_entries_and_old_aliases(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::create(['name' => 'Área sem usos', 'formation' => 'Parte Complementar']);
        foreach (range(1, 13) as $number) {
            $entry = HistoryCurriculumComponent::create(['name' => 'Componente '.$number, 'knowledge_area_id' => $area->id, 'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'active' => true]);
            $entry->forceFill(['matrix_origin' => true])->save();
        }
        $school = School::create(['name' => 'Escola preservada']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz preservada', 'stage' => 'fundamental']);
        $matrix = $course->components()->create(['name' => 'Componente preservado', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id')]);
        $target = $matrix->historyCatalogEntries()->sole();
        HistoryCurriculumAlias::create(['knowledge_area_id' => $area->id, 'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'name' => 'Nome antigo', 'history_curriculum_component_id' => $target->id]);
        HistoryCurriculumAlias::create(['knowledge_area_id' => $area->id, 'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'name' => 'Outro nome', 'history_curriculum_component_id' => $entry->id]);
        $snapshot = [$matrix->fresh()->getAttributes(), $target->fresh()->getAttributes(), $course->fresh()->getAttributes()];
        $counts = KnowledgeArea::withUsageCounts()->findOrFail($area->id);
        $this->assertSame(0, $counts->usageCount());
        $this->assertSame(13, $counts->history_catalog_components_count);
        foreach ([route('knowledge-areas.index', ['q' => $area->name]), route('knowledge-areas.usages', $area)] as $url) {
            $this->get($url)->assertOk()->assertSee('aria-label="Excluir área: '.$area->name.'"', false)
                ->assertSee('13 componentes do catálogo sem uso?');
        }
        $this->delete(route('knowledge-areas.destroy', $area))->assertSessionHasNoErrors()->assertRedirect(route('knowledge-areas.index'));
        $this->assertDatabaseMissing('knowledge_areas', ['id' => $area->id]);
        $this->assertDatabaseMissing('history_curriculum_components', ['knowledge_area_id' => $area->id]);
        $this->assertDatabaseMissing('history_curriculum_aliases', ['knowledge_area_id' => $area->id]);
        $this->assertSame($snapshot, [$matrix->fresh()->getAttributes(), $target->fresh()->getAttributes(), $course->fresh()->getAttributes()]);
    }

    public function test_history_use_is_counted_once_and_blocks_area_deletion_without_partial_cleanup(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::create(['name' => 'Área com histórico', 'formation' => 'Parte Complementar']);
        $entries = collect(['fundamental', 'medio'])->map(fn ($stage) => HistoryCurriculumComponent::create(['name' => 'Componente utilizado', 'knowledge_area_id' => $area->id, 'education_stage' => $stage, 'formation' => 'Parte Complementar', 'active' => true]));
        $unused = HistoryCurriculumComponent::create(['name' => 'Componente sem uso', 'knowledge_area_id' => $area->id, 'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'active' => true]);
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'school_id' => School::create(['name' => 'Escola de teste'])->id, 'title' => 'Histórico de teste']);
        $component = $history->components()->create(['name' => 'Componente utilizado', 'knowledge_area' => $area->name, 'formation' => 'Parte Complementar']);
        $year = $history->years()->create(['label' => '8º Ano', 'source' => 'manual']);
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'workload_hours' => 80]);
        foreach ($entries as $entry) {
            $entry->historyComponents()->attach($component);
        }
        $snapshot = [$record->fresh()->getAttributes(), $component->fresh()->getAttributes()];
        $counts = KnowledgeArea::withUsageCounts()->findOrFail($area->id);
        $this->assertSame(0, $counts->matrix_uses_count);
        $this->assertSame(1, $counts->history_uses_count);
        $this->assertSame(1, $counts->usageCount());
        $this->get(route('knowledge-areas.index', ['q' => $area->name]))->assertOk()
            ->assertSee('Matrizes: 0. Históricos: 1.')
            ->assertDontSee('aria-label="Excluir área: '.$area->name.'"', false);
        $this->delete(route('knowledge-areas.destroy', $area))->assertSessionHasErrors('area');
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $unused->id]);
        $this->assertDatabaseHas('knowledge_areas', ['id' => $area->id]);
        $this->assertSame($snapshot, [$record->fresh()->getAttributes(), $component->fresh()->getAttributes()]);
    }

    public function test_matrix_usage_blocks_deletion_even_when_its_catalog_definition_is_in_another_area(): void
    {
        $this->actingAs($this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $area = KnowledgeArea::create(['name' => 'Área da matriz', 'formation' => 'Parte Complementar']);
        $school = School::create(['name' => 'Escola da matriz']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz protegida', 'stage' => 'fundamental']);
        $matrix = $course->components()->create(['name' => 'Componente protegido', 'knowledge_area_id' => $area->id, 'active' => false]);
        $entry = $matrix->historyCatalogEntries()->sole();
        $otherArea = KnowledgeArea::create(['name' => 'Outra área de referência', 'formation' => 'Parte Complementar']);
        DB::table('history_curriculum_components')->where('id', $entry->id)->update(['knowledge_area_id' => $otherArea->id]);
        $snapshot = [$matrix->fresh()->getAttributes(), $entry->fresh()->getAttributes()];
        foreach ([$area, $otherArea] as $usedArea) {
            $counts = KnowledgeArea::withUsageCounts()->findOrFail($usedArea->id);
            $this->assertSame(1, $counts->matrix_uses_count);
            $this->assertSame(1, $counts->usageCount());
            $this->delete(route('knowledge-areas.destroy', $usedArea))->assertSessionHasErrors('area');
            $this->assertDatabaseHas('knowledge_areas', ['id' => $usedArea->id]);
        }
        $this->assertSame($snapshot, [$matrix->fresh()->getAttributes(), $entry->fresh()->getAttributes()]);
    }

    private function userWithRole(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa '.$role, 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
