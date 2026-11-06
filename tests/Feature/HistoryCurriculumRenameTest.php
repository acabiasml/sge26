<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumComponentsTable;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\HistoryCurriculumAlias;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryCurriculumRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_rename_all_original_matrices_even_without_history_usages(): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $one = $this->matrix('Introdução à Língua Italiana');
        $two = $this->matrix('Introdução à Língua Italiana');
        $entry = $one->historyCatalogEntries()->sole();
        $before = collect([$entry, $one, $two])->map(fn ($model) => Arr::except($model->fresh()->getAttributes(), ['name', 'updated_at']))->all();
        $this->assertSame(0, $entry->historyComponents()->count());
        Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Introdução à Língua Italiana')
            ->assertSeeHtml('data-target="#catalog-rename-'.$entry->id.'"')
            ->assertSeeHtml('action="'.route('history-curriculum.rename', $entry).'"');

        $this->from(route('history-curriculum.index'))->put(route('history-curriculum.rename', $entry), [
            'name' => '  introdução   à língua italiana II  ',
            'education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'knowledge_area_id' => 999999, 'active' => false,
        ])->assertRedirect(route('history-curriculum.index'))->assertSessionHasNoErrors();

        foreach ([$entry, $one, $two] as $model) {
            $this->assertSame('introdução à língua italiana II', $model->fresh()->name);
        }
        $this->assertSame($before, collect([$entry, $one, $two])->map(fn ($model) => Arr::except($model->fresh()->getAttributes(), ['name', 'updated_at']))->all());
        app(MatrixHistoryCatalog::class)->importAll();
        $this->assertSame($entry->id, $one->historyCatalogEntries()->sole()->id);
        $this->assertSame($entry->id, $two->historyCatalogEntries()->sole()->id);
        $this->assertDatabaseMissing('history_curriculum_components', ['name' => $entry->name]);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'auditable_id' => $entry->id,
            'metadata->operation' => 'history-curriculum:rename', 'metadata->updated_matrix_components' => 2]);
    }

    public function test_rename_preserves_system_and_external_results_and_their_existing_classification(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $matrix = $this->matrix('Italiano Antigo');
        $entry = $matrix->historyCatalogEntries()->sole();
        $records = collect();
        $components = collect();
        foreach (['external', 'system'] as $source) {
            $history = $this->history();
            $year = $history->years()->create(['label' => '1º Ano', 'stage' => 'Ensino Médio', 'source' => $source, 'year' => '2025']);
            $row = $history->components()->create(['name' => $entry->name, 'knowledge_area' => $entry->area->name, 'formation' => 'Parte Complementar', 'position' => 4, 'module_label' => 'Módulo I']);
            $row->catalogEntries()->attach($entry);
            $components->push($row);
            $records->push($row->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $source === 'system' ? $matrix->id : null,
                'score_label' => '8,0', 'score_numeric' => 8, 'workload_hours' => 80, 'completed_workload_hours' => 76, 'absences' => 4, 'frequency_percentage' => 95, 'result' => 'Aprovado']));
        }
        $beforeRecords = $records->map(fn ($record) => $record->fresh()->getAttributes())->all();
        $beforeComponents = $components->map(fn ($row) => Arr::except($row->fresh()->getAttributes(), ['name', 'updated_at']))->all();
        $beforeLinks = DB::table('history_catalog_usages')->orderBy('student_academic_history_component_id')->get()->toArray();
        $this->put(route('history-curriculum.rename', $entry), ['name' => 'Língua Italiana'])->assertSessionHasNoErrors();
        $this->assertSame($beforeRecords, $records->map(fn ($record) => $record->fresh()->getAttributes())->all());
        $this->assertSame($beforeComponents, $components->map(fn ($row) => Arr::except($row->fresh()->getAttributes(), ['name', 'updated_at']))->all());
        $this->assertEquals($beforeLinks, DB::table('history_catalog_usages')->orderBy('student_academic_history_component_id')->get()->toArray());
        foreach ($components as $row) {
            $this->assertSame('Língua Italiana', $row->fresh()->name);
            app(HistoryCatalogIntegrator::class)->syncHistory($row->history->fresh());
            $this->assertSame($entry->id, $row->catalogEntries()->sole()->id);
        }
    }

    public function test_catalog_creation_editing_and_later_matrix_and_history_saves_preserve_the_chosen_case(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $existingMatrix = $this->matrix('Componente da matriz');
        $data = ['name' => 'LIN. inglesa ii', 'education_stage' => 'medio', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => $existingMatrix->knowledge_area_id];
        $this->post(route('history-curriculum.store'), $data)->assertSessionHasNoErrors();
        $entry = HistoryCurriculumComponent::where('name', 'LIN. inglesa ii')->sole();
        $this->assertSame('LIN. inglesa ii', $entry->name);
        $data['name'] = 'língua INGLESA ii';
        $this->put(route('history-curriculum.update', $entry), $data)->assertSessionHasNoErrors();
        $this->assertSame($data['name'], $entry->fresh()->name);

        $course = $existingMatrix->course;
        $this->post(route('academic-years.courses.components.store', [$course->academicYear, $course]), [
            'catalog_component_id' => $entry->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80,
        ])->assertSessionHasNoErrors();
        $matrix = $course->components()->where('id', '!=', $existingMatrix->id)->sole();
        $history = $this->history();
        $year = $history->years()->create(['label' => '1º Ano', 'stage' => 'Ensino Médio', 'source' => 'external']);
        $row = $history->components()->create(['name' => $data['name'], 'formation' => $entry->formation, 'knowledge_area' => $entry->area->name]);
        $row->catalogEntries()->attach($entry);
        $record = $row->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'workload_hours' => 80]);
        $recordBefore = $record->fresh()->getAttributes();
        $this->assertSame($data['name'], $matrix->name);
        $this->assertSame($data['name'], $row->fresh()->name);

        $name = 'LÍNGUA inglesa ii';
        $this->put(route('history-curriculum.rename', $entry), ['name' => $name])->assertSessionHasNoErrors();
        $matrix->refresh()->update(['workload_hours' => 90]);
        $row->refresh()->update(['position' => 2]);
        $entry->refresh()->update(['active' => true]);
        app(MatrixHistoryCatalog::class)->importAll();
        app(HistoryCatalogIntegrator::class)->syncHistory($history->fresh());
        foreach ([$entry, $matrix, $row] as $model) {
            $this->assertSame($name, $model->fresh()->name);
        }
        $this->assertSame($entry->id, $matrix->historyCatalogEntries()->sole()->id);
        $this->assertSame($entry->id, $row->catalogEntries()->sole()->id);
        $this->assertSame($recordBefore, $record->fresh()->getAttributes());
    }

    public function test_case_only_rename_keeps_the_same_entry_and_updates_its_links(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $matrix = $this->matrix('Língua Inglesa');
        $entry = $matrix->historyCatalogEntries()->sole();
        foreach (['LÍNGUA INGLESA', 'língua inglesa', 'Língua Inglesa'] as $name) {
            $this->put(route('history-curriculum.rename', $entry), ['name' => $name])->assertSessionHasNoErrors();
            $this->assertSame($name, $entry->fresh()->name);
            $matrix->refresh()->update(['workload_hours' => 85]);
            $this->assertSame($name, $matrix->fresh()->name);
            $this->assertSame($entry->id, $matrix->historyCatalogEntries()->sole()->id);
        }
    }

    public function test_orphaned_matrix_reference_can_be_renamed_and_full_edit_remains_protected(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $entry = $this->matrix('Componente sem Matriz')->historyCatalogEntries()->sole();
        $entry->matrixComponents()->each(fn ($matrix) => $matrix->delete());
        $history = $this->history();
        $year = $history->years()->create(['label' => '1º Ano', 'source' => 'system']);
        $row = $history->components()->create(['name' => $entry->name, 'formation' => $entry->formation, 'knowledge_area' => $entry->area->name]);
        $row->catalogEntries()->attach($entry);
        $record = $row->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '9,0']);
        $this->put(route('history-curriculum.update', $entry), ['name' => 'Outro'])->assertForbidden();
        $this->put(route('history-curriculum.rename', $entry), ['name' => 'Componente Corrigido'])->assertSessionHasNoErrors();
        $this->assertSame('Componente Corrigido', $row->fresh()->name);
        $this->assertSame('9,0', $record->fresh()->score_label);
        $this->assertSame($entry->id, $row->catalogEntries()->sole()->id);
    }

    public function test_invalid_names_and_names_reserved_by_another_entry_do_not_partially_rename(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $matrix = $this->matrix('Nome Original');
        $entry = $matrix->historyCatalogEntries()->sole();
        $other = $this->matrix('Nome Existente')->historyCatalogEntries()->sole();
        HistoryCurriculumAlias::create($entry->only(['education_stage', 'formation', 'knowledge_area_id']) + ['name' => 'Nome Incorporado', 'history_curriculum_component_id' => $other->id]);
        $before = [$entry->fresh()->getAttributes(), $matrix->fresh()->getAttributes()];
        foreach (['', '   ', ['Inválido'], str_repeat('a', 256), '  Nome    Existente ', 'Nome Incorporado'] as $name) {
            $this->put(route('history-curriculum.rename', $entry), ['name' => $name])->assertSessionHasErrors('name');
            $this->assertSame($before, [$entry->fresh()->getAttributes(), $matrix->fresh()->getAttributes()]);
        }
        $this->assertDatabaseCount('history_curriculum_aliases', 1);
        $this->assertDatabaseMissing('audit_logs', ['metadata->operation' => 'history-curriculum:rename']);
    }

    public function test_only_administrators_can_rename_catalog_components(): void
    {
        $entry = $this->matrix('Componente Protegido')->historyCatalogEntries()->sole();
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $this->actingAs($this->user($role))->put(route('history-curriculum.rename', $entry), ['name' => 'Tentativa'])->assertForbidden();
        }
        $this->assertSame('Componente Protegido', $entry->fresh()->name);
    }

    public function test_rename_modal_has_only_the_name_field_and_recovers_validation_errors_off_page(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $entry = $this->matrix('Zzz Italiano')->historyCatalogEntries()->sole();
        $marker = 'catalog-rename-'.$entry->id;
        $response = $this->withSession(['_old_input' => ['catalog_form' => $marker, 'name' => 'Nome Rejeitado'],
            'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['name' => 'Nome já utilizado.']))])
            ->get(route('history-curriculum.index'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $inputs = $xpath->query('//div[@id="'.$marker.'"]//input[not(@type="hidden")]');
        $this->assertSame(1, $inputs->length);
        $this->assertSame('name', $inputs->item(0)->getAttribute('name'));
        $this->assertSame('Nome Rejeitado', $inputs->item(0)->getAttribute('value'));
        $this->assertSame('true', $inputs->item(0)->getAttribute('aria-invalid'));
        $this->assertSame(0, $xpath->query('//div[@id="'.$marker.'"]//select')->length);
        $this->assertSame(1, $xpath->query('//div[@id="'.$marker.'-error"]')->length);
    }

    private function matrix(string $name): CurriculumComponent
    {
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz de teste', 'stage' => 'medio']);
        $area = KnowledgeArea::firstOrCreate(['name' => 'Aprofundamento de Estudos'], ['formation' => 'Itinerário Formativo', 'active' => true]);

        return $course->components()->create(['name' => $name, 'knowledge_area_id' => $area->id, 'workload_hours' => 80, 'weekly_lessons' => 2, 'active' => true]);
    }

    private function history(): StudentAcademicHistory
    {
        return StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'school_id' => School::create(['name' => 'Escola de teste'])->id, 'title' => 'Histórico', 'education_stage' => 'medio']);
    }

    private function user(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa de teste', 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
