<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\HistoryCatalogIntegrator;
use App\Support\MatrixHistoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryCurriculumMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_adopts_the_matrix_destination_across_stage_formation_and_area_without_changing_matrix_or_results(): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $source = $this->entry('Inglês antigo');
        $matrix = $this->matrixComponent('Língua Inglesa', 'medio', 'Linguagens e suas Tecnologias', 'Parte Complementar');
        $target = app(MatrixHistoryCatalog::class)->syncComponent($matrix);
        $history = $this->history();
        $one = $history->components()->create(['name' => $source->name, 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $two = $history->components()->create(['name' => $target->name, 'formation' => $target->formation, 'knowledge_area' => $target->area->name]);
        $year = $history->years()->create(['label' => '8º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'manual']);
        $one->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'workload_hours' => 80, 'absences' => 3]);
        $two->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '7,0', 'workload_hours' => 90, 'absences' => 4]);
        $source->historyComponents()->attach($one);
        $target->historyComponents()->attach([$one->id, $two->id]);
        $snapshot = [$matrix->fresh()->getAttributes(), $matrix->course->getAttributes(), $matrix->area->getAttributes(), $one->records()->sole()->getAttributes(), $two->records()->sole()->getAttributes(), $year->fresh()->getAttributes(), $target->fresh()->getAttributes()];
        $this->actingAs($admin)->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('history-curriculum.index'));
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        $this->assertSame($snapshot, [$matrix->fresh()->getAttributes(), $matrix->course->getAttributes(), $matrix->area->getAttributes(), $one->records()->sole()->getAttributes(), $two->records()->sole()->getAttributes(), $year->fresh()->getAttributes(), $target->fresh()->getAttributes()]);
        $this->assertSame($target->name, $one->fresh()->name);
        $this->assertSame($target->formation, $one->fresh()->formation);
        $this->assertSame($target->area->name, $one->fresh()->knowledge_area);
        $this->assertSame(2, $target->historyComponents()->count());
        app(HistoryCatalogIntegrator::class)->syncHistory($history->fresh());
        $this->assertSame($target->id, $one->catalogEntries()->sole()->id);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'auditable_id' => $target->id, 'metadata->operation' => 'history-curriculum:merge']);
    }

    public function test_components_from_two_matrices_cannot_be_merged_or_edited_even_with_a_forged_request(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $first = $this->matrixComponent('Lin. Inglesa');
        $second = $this->matrixComponent('Língua Inglesa');
        $source = app(MatrixHistoryCatalog::class)->syncComponent($first);
        $target = app(MatrixHistoryCatalog::class)->syncComponent($second);
        $before = [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()];
        $this->get(route('history-curriculum.merge-form', $source))->assertForbidden();
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasErrors('catalog_component');
        $this->put(route('history-curriculum.update', $source), ['name' => 'Língua Inglesa'])->assertForbidden();
        $this->delete(route('history-curriculum.destroy', $source))->assertForbidden();
        $html = $this->get(route('history-curriculum.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('history-curriculum.merge-form', $source), $html);
        $this->assertStringNotContainsString('id="catalog-edit-'.$source->id.'"', $html);
        $this->assertSame($before, [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $first->id, 'history_curriculum_component_id' => $source->id]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $second->id, 'history_curriculum_component_id' => $target->id]);
    }

    public function test_manual_catalog_entries_and_unmapped_system_records_are_not_valid_merge_sources_or_destinations(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $source = $this->entry('Inglês antigo');
        $notMatrix = $this->entry('Outro nome manual');
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $notMatrix->id])->assertSessionHasErrors('target_component_id');
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $source->id])->assertSessionHasErrors('target_component_id');
        $this->post(route('history-curriculum.merge', $source), [])->assertSessionHasErrors('target_component_id');
        $history = $this->history();
        $year = $history->years()->create(['label' => '8º Ano', 'source' => 'system']);
        $component = $history->components()->create(['name' => $source->name, 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '9,0']);
        $source->historyComponents()->attach($component);
        $target = app(MatrixHistoryCatalog::class)->syncComponent($this->matrixComponent('Lin. Inglesa'));
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasErrors('catalog_component');
        $this->assertSame('9,0', $record->fresh()->score_label);
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $source->id]);
    }

    public function test_matrix_origins_stay_current_and_aliases_never_override_a_real_matrix_name(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $source = $this->entry('Idioma antigo');
        $matrix = $this->matrixComponent('Lin. Inglesa');
        $target = app(MatrixHistoryCatalog::class)->syncComponent($matrix);
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $history = $this->history();
        $year = $history->years()->create(['label' => '8º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'manual']);
        $legacy = $history->components()->create(['name' => 'Idioma antigo', 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $legacy->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '6,0']);
        app(HistoryCatalogIntegrator::class)->syncHistory($history);
        $this->assertSame($target->id, $legacy->catalogEntries()->sole()->id);
        $this->assertSame('Lin. Inglesa', $legacy->fresh()->name);
        $actual = $this->matrixComponent('Idioma antigo');
        $canonical = app(MatrixHistoryCatalog::class)->syncComponent($actual);
        $new = $history->components()->create(['name' => 'Idioma antigo', 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $new->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '7,0']);
        app(HistoryCatalogIntegrator::class)->syncHistory($history);
        $this->assertSame($canonical->id, $new->catalogEntries()->sole()->id);
        $matrix->update(['name' => 'Língua Estrangeira']);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $matrix->id]);
        $this->assertSame('Língua Estrangeira', app(MatrixHistoryCatalog::class)->syncComponent($matrix)->name);
        $this->assertTrue($target->fresh()->matrix_origin);
    }

    public function test_merge_form_lists_only_matrix_destinations_and_other_roles_cannot_merge(): void
    {
        $source = $this->entry('Inglês antigo');
        $manual = $this->entry('Apenas cadastro');
        $target = app(MatrixHistoryCatalog::class)->syncComponent($this->matrixComponent('Lin. Inglesa', 'medio', 'Linguagens e suas Tecnologias'));
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $this->get(route('history-curriculum.merge-form', $source))->assertOk()->assertSee('Lin. Inglesa')->assertDontSee($manual->name)->assertDontSee('name="keep"', false);
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('history-curriculum.merge-form', $source))->assertForbidden();
            $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertForbidden();
        }
    }

    public function test_matrix_backfill_reads_database_defaults_and_keeps_imported_spelling_without_repeated_placeholder_areas(): void
    {
        $initial = $this->matrixComponent('Inicial');
        $one = $initial->course->components()->create(['name' => 'Primeiro sem área']);
        $two = $initial->course->components()->create(['name' => 'Segundo sem área']);
        DB::table('curriculum_components')->where('id', $one->id)->update(['name' => 'LIN. INGLESA']);
        $snapshot = [$one->fresh()->getAttributes(), $two->fresh()->getAttributes()];
        app(MatrixHistoryCatalog::class)->importAll();
        $entry = $one->historyCatalogEntries()->sole();
        $this->assertSame('LIN. INGLESA', $entry->name);
        $this->assertSame($one->fresh()->active, $entry->active);
        $this->assertSame($entry->knowledge_area_id, $two->historyCatalogEntries()->sole()->knowledge_area_id);
        $this->assertSame($snapshot, [$one->fresh()->getAttributes(), $two->fresh()->getAttributes()]);
    }

    public function test_areas_and_components_use_identical_navigation_with_the_correct_active_link(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        foreach (['knowledge-areas.index', 'history-curriculum.index'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(2, $xpath->query('//nav[@aria-label="Cadastro curricular"]/a')->length);
            $this->assertSame(route($route), $xpath->query('//nav[@aria-label="Cadastro curricular"]/a[@aria-current="page"]')->item(0)->getAttribute('href'));
        }
    }

    private function entry(string $name): HistoryCurriculumComponent
    {
        return HistoryCurriculumComponent::firstOrCreate(['name' => $name, 'formation' => 'Formação Geral Básica', 'education_stage' => 'fundamental', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id')], ['active' => true]);
    }

    private function matrixComponent(string $name, string $stage = 'fundamental', string $areaName = 'Linguagens', string $formation = 'Formação Geral Básica'): CurriculumComponent
    {
        $school = School::create(['name' => 'Escola da matriz']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz de teste', 'stage' => $stage]);
        $area = KnowledgeArea::firstOrCreate(['name' => $areaName], ['formation' => $formation, 'active' => true]);
        if ($area->formation !== $formation) {
            $area->update(['formation' => $formation]);
        }

        return $course->components()->create(['name' => $name, 'knowledge_area_id' => $area->id, 'workload_hours' => 80, 'active' => true]);
    }

    private function history(): StudentAcademicHistory
    {
        return StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'school_id' => School::create(['name' => 'Escola de teste'])->id, 'title' => 'Histórico', 'education_stage' => 'fundamental']);
    }

    private function user(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa de teste', 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
