<?php

namespace Tests\Feature;

use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\HistoryCatalogIntegrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistoryCurriculumMergeTest extends TestCase
{
    use RefreshDatabase;

    public static function choices(): array
    {
        return [['current'], ['other']];
    }

    #[DataProvider('choices')]
    public function test_merge_retains_chosen_name_and_all_student_records_and_combines_shared_links(string $choice): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $first = $this->entry('Inglês');
        $second = $this->entry('Língua Inglesa', false);
        $history = $this->history();
        $one = $history->components()->create(['name' => 'Inglês', 'formation' => $first->formation, 'knowledge_area' => $first->area->name]);
        $two = $history->components()->create(['name' => 'Língua Inglesa', 'formation' => $second->formation, 'knowledge_area' => $second->area->name]);
        $year = $history->years()->create(['label' => '8º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'manual']);
        $one->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'workload_hours' => 80, 'absences' => 3]);
        $two->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '7,0', 'workload_hours' => 90, 'absences' => 4]);
        $first->historyComponents()->attach($one);
        $second->historyComponents()->attach([$one->id, $two->id]);
        $snapshot = [$one->records()->sole()->getAttributes(), $two->records()->sole()->getAttributes()];
        $keep = $choice === 'current' ? $first : $second;
        $remove = $choice === 'current' ? $second : $first;
        $this->actingAs($admin)->from(route('history-curriculum.merge-form', $first))
            ->post(route('history-curriculum.merge', $first), ['other_component_id' => $second->id, 'keep' => $choice])
            ->assertSessionHasNoErrors()->assertRedirect(route('history-curriculum.index'));
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $remove->id]);
        $this->assertSame($keep->name, $keep->fresh()->name);
        $this->assertTrue($keep->fresh()->active);
        $this->assertSame(2, $keep->historyComponents()->count());
        $this->assertSame($snapshot, [$one->records()->sole()->getAttributes(), $two->records()->sole()->getAttributes()]);
        $this->assertSame(2, $history->components()->count());
        $this->assertDatabaseHas('history_curriculum_aliases', ['name' => $remove->name, 'history_curriculum_component_id' => $keep->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'auditable_id' => $keep->id, 'metadata->operation' => 'history-curriculum:merge']);
        $new = $history->components()->create(['name' => $remove->name, 'formation' => $keep->formation, 'knowledge_area' => $keep->area->name]);
        $new->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '6,0']);
        app(HistoryCatalogIntegrator::class)->syncHistory($history);
        $this->assertSame($keep->id, $new->catalogEntries()->sole()->id);
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $remove->id]);
        $this->assertSame(1, HistoryCurriculumComponent::where('education_stage', 'fundamental')->where('knowledge_area_id', $keep->knowledge_area_id)->whereIn('name', [$first->name, $second->name])->count());
    }

    public function test_merge_rejects_self_missing_choice_incompatible_context_and_other_roles(): void
    {
        $first = $this->entry('Inglês');
        $second = $this->entry('Idioma Inglês');
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $this->post(route('history-curriculum.merge', $first), ['other_component_id' => $first->id, 'keep' => 'current'])->assertSessionHasErrors('other_component_id');
        $this->post(route('history-curriculum.merge', $first), ['other_component_id' => $second->id])->assertSessionHasErrors('keep');
        foreach ([['education_stage' => 'medio'], ['formation' => 'Parte Diversificada'], ['knowledge_area_id' => KnowledgeArea::where('name', 'Matemática')->value('id')]] as $changes) {
            $second->update(array_replace(['education_stage' => $first->education_stage, 'formation' => $first->formation, 'knowledge_area_id' => $first->knowledge_area_id], $changes));
            $this->post(route('history-curriculum.merge', $first), ['other_component_id' => $second->id, 'keep' => 'current'])->assertSessionHasErrors('other_component_id');
        }
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $first->id]);
        $this->assertDatabaseHas('history_curriculum_components', ['id' => $second->id]);
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('history-curriculum.merge-form', $first))->assertForbidden();
            $this->post(route('history-curriculum.merge', $first), ['other_component_id' => $second->id, 'keep' => 'current'])->assertForbidden();
        }
    }

    public function test_repeated_merges_redirect_old_names_to_the_final_component_and_block_recreating_them(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $first = $this->entry('Inglês');
        $second = $this->entry('Idioma Inglês');
        $third = $this->entry('Língua Inglesa');
        $this->post(route('history-curriculum.merge', $first), ['other_component_id' => $second->id, 'keep' => 'other'])->assertSessionHasNoErrors();
        $this->post(route('history-curriculum.merge', $second), ['other_component_id' => $third->id, 'keep' => 'other'])->assertSessionHasNoErrors();
        $this->assertSame(2, HistoryCurriculumAlias::where('history_curriculum_component_id', $third->id)->count());
        $this->post(route('history-curriculum.store'), $first->only(['name', 'formation', 'education_stage', 'knowledge_area_id']) + ['active' => 1])->assertSessionHasErrors('name');
        $this->put(route('history-curriculum.update', $third), ['name' => 'Inglês'] + $third->only(['formation', 'education_stage', 'knowledge_area_id', 'active']))->assertSessionHasNoErrors();
    }

    public function test_areas_and_components_use_identical_navigation_with_the_correct_active_link(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        foreach (['knowledge-areas.index', 'history-curriculum.index'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Cadastro curricular"]')->length);
            $this->assertSame(2, $xpath->query('//nav[@aria-label="Cadastro curricular"]/a')->length);
            $this->assertSame(route($route), $xpath->query('//nav[@aria-label="Cadastro curricular"]/a[@aria-current="page"]')->item(0)->getAttribute('href'));
        }
    }

    private function entry(string $name, bool $active = true): HistoryCurriculumComponent
    {
        return HistoryCurriculumComponent::updateOrCreate(['name' => $name, 'formation' => 'Formação Geral Básica', 'education_stage' => 'fundamental', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id')], ['active' => $active]);
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
