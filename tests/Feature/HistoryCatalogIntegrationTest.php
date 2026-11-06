<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumUsagesTable;
use App\Models\HistoryCurriculumComponent;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\User;
use App\Support\HistoryCatalogIntegrator;
use App\Support\HistoryCurriculumCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryCatalogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_incorporates_legacy_items_without_duplicates_or_changing_student_data(): void
    {
        $known = $this->historyComponent('fundamental', 'Linguagens e Suas Tecnologias', 'Língua Portuguesa');
        $legacy = $this->historyComponent('fundamental', 'Saberes Locais', 'Práticas Comunitárias', 'Parte Diversificada');
        $duplicate = $this->historyComponent('fundamental', 'saberes locais', 'práticas comunitárias', 'Parte Diversificada');
        $before = [$legacy->fresh()->getAttributes(), $legacy->records()->sole()->getAttributes(), $legacy->history->years()->sole()->getAttributes()];
        $knownEntry = HistoryCurriculumComponent::where('education_stage', 'fundamental')->where('name', 'Língua Portuguesa')->sole();
        $knownEntry->update(['active' => false]);
        Schema::drop('history_catalog_usages');
        (require database_path('migrations/2026_11_05_000003_integrate_history_components_into_curriculum_catalog.php'))->up();
        $entry = $legacy->catalogEntries()->sole();
        $this->assertSame('Saberes Locais', $entry->area->name);
        $this->assertSame(2, $entry->historyComponents()->count());
        $this->assertSame($entry->id, $duplicate->catalogEntries()->sole()->id);
        $this->assertSame($knownEntry->id, $known->catalogEntries()->sole()->id);
        $this->assertFalse($knownEntry->fresh()->active);
        $this->assertSame($before, [$legacy->fresh()->getAttributes(), $legacy->records()->sole()->getAttributes(), $legacy->history->years()->sole()->getAttributes()]);
        $total = HistoryCurriculumComponent::count();
        app(HistoryCatalogIntegrator::class)->importAll();
        $this->assertSame($total, HistoryCurriculumComponent::count());
        $this->assertSame(0, StudentAcademicHistoryComponent::whereDoesntHave('catalogEntries')->count());
        $this->assertTrue(HistoryCurriculumCatalog::contains('fundamental', 'Parte Diversificada', 'Saberes Locais', 'Práticas Comunitárias'));
    }

    public function test_mixed_stage_records_link_to_the_correct_stage_and_unknown_stage_is_kept_inactive(): void
    {
        $component = $this->historyComponent(null, 'Matemática', 'Matemática');
        $component->history->years()->sole()->update(['stage' => 'Ensino Fundamental']);
        $year = $component->history->years()->create(['label' => '1º Ano', 'stage' => 'Ensino Médio', 'transcript_mode' => 'detailed', 'source' => 'manual']);
        $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '9,0']);
        $unknown = $this->historyComponent(null, 'Saberes Locais', 'Oficinas');
        app(HistoryCatalogIntegrator::class)->importAll();
        $this->assertEqualsCanonicalizing(['fundamental', 'medio'], $component->catalogEntries()->pluck('education_stage')->all());
        $this->assertSame('Matemática e suas Tecnologias', $component->catalogEntries()->where('education_stage', 'medio')->sole()->area->name);
        $entry = $unknown->catalogEntries()->sole();
        $this->assertSame(HistoryCurriculumCatalog::UNCLASSIFIED_STAGE, $entry->education_stage);
        $this->assertFalse($entry->active);
        $this->assertSame('9,0', $component->records()->where('student_academic_history_year_id', $year->id)->sole()->score_label);
        $this->admin();
        $this->get(route('history-curriculum.index'))->assertOk()->assertSee('Etapa não identificada');
    }

    public function test_catalog_usages_are_paginated_searchable_and_administrator_only_and_protect_used_entries(): void
    {
        foreach (range(1, 11) as $number) {
            $component = $this->historyComponent('fundamental', 'Saberes Locais', 'Oficinas');
            $component->history->student->update(['full_name' => sprintf('Estudante %02d', $number)]);
        }
        app(HistoryCatalogIntegrator::class)->importAll();
        $entry = $component->catalogEntries()->sole();
        $admin = $this->admin();
        $this->get(route('history-curriculum.usages', $entry))->assertOk()->assertSeeLivewire(HistoryCurriculumUsagesTable::class);
        Livewire::test(HistoryCurriculumUsagesTable::class, ['catalogId' => $entry->id])
            ->assertSet('perPage', 10)->assertSet('paginationCurrentCount', 10)
            ->assertSee('Estudante 01')->assertDontSee('Estudante 11')
            ->call('gotoPage', 2)->assertSee('Estudante 11')
            ->set('search', 'Estudante 03')->assertSee('Estudante 03')->assertDontSee('Estudante 01');
        $this->delete(route('history-curriculum.destroy', $entry))->assertSessionHasErrors('catalog_component');
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $entry->id]);
        $admin->person->schoolRoles()->delete();
        $admin->unsetRelations();
        $this->get(route('history-curriculum.usages', $entry))->assertForbidden();
        Livewire::test(HistoryCurriculumUsagesTable::class, ['catalogId' => $entry->id])->assertForbidden();
    }

    private function historyComponent(?string $stage, string $area, string $name, string $formation = 'Formação Geral Básica'): StudentAcademicHistoryComponent
    {
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'school_id' => School::create(['name' => 'Escola de teste'])->id, 'title' => 'Histórico', 'education_stage' => $stage]);
        $year = $history->years()->create(['label' => '1º Ano', 'stage' => $stage ? 'Ensino '.($stage === 'fundamental' ? 'Fundamental' : 'Médio') : null, 'transcript_mode' => 'detailed', 'workload_hours' => 800, 'source' => 'manual']);
        $component = $history->components()->create(['name' => $name, 'knowledge_area' => $area, 'formation' => $formation]);
        $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'workload_hours' => 80, 'absences' => 2]);

        return $component;
    }

    private function admin(): User
    {
        $person = Person::create(['full_name' => 'Administrador de teste', 'cpf' => '12345678901', 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $admin = User::factory()->create(['person_id' => $person->id]);
        $this->actingAs($admin);

        return $admin;
    }
}
