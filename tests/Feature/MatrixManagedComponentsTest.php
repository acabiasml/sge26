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
use App\Models\StudentAcademicHistory;
use App\Support\CurriculumCatalog;
use App\Support\MatrixHistoryCatalog;
use App\Console\Commands\ImportLegacyData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $options = $xpath->query('//select[@id="'.$id.'" and @name="catalog_component_id" and @required]/option[@value!=""]');

        return array_map(fn ($option) => $option->getAttribute('data-name'), iterator_to_array($options));
    }

    public function test_create_and_edit_offer_registered_components_regardless_of_legacy_status_in_alphabetical_order(): void
    {
        $course = $this->course();
        $zeta = $this->entry('Zoologia');
        $this->entry('Álgebra');
        $this->entry('Biologia');
        $this->entry('Apenas no médio', 'medio');
        $legacyEntry = $this->entry('Componente inativo', active: false);
        \Illuminate\Support\Facades\DB::table('history_curriculum_components')->where('id', $legacyEntry->id)->update(['active' => false]);
        $inactiveArea = KnowledgeArea::create(['name' => 'Área inativa', 'formation' => 'Formação Técnica Profissional', 'active' => false]);
        $this->entry('Com área inativa', area: $inactiveArea);
        $this->entry('Biologia', area: KnowledgeArea::create(['name' => 'Outra área', 'formation' => 'Formação Técnica Profissional', 'active' => true]));
        $component = $course->components()->create(['name' => $zeta->name, 'knowledge_area_id' => $zeta->knowledge_area_id, 'workload_hours' => 80]);
        $before = [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()];
        $create = $this->get(route('academic-years.courses.show', [$course->academicYear, $course]))->assertOk();
        $edit = $this->get(route('academic-years.courses.components.show', [$course->academicYear, $course, $component]))->assertOk();
        $this->assertSame(['Álgebra', 'Biologia', 'Biologia', 'Componente Inativo', 'Zoologia'], $this->names($create, 'new_component_name'));
        $this->assertSame(['Álgebra', 'Biologia', 'Biologia', 'Componente Inativo', 'Zoologia'], $this->names($edit, 'component_name'));
        $this->assertSame($before, [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()]);
        $create->assertDontSee('Gerenciar catálogo');
    }

    public function test_management_can_save_catalog_names_but_cannot_create_or_rename_with_free_text(): void
    {
        $course = $this->course();
        $entry = $this->entry('Componente cadastrado');
        $other = $this->entry('Outro componente cadastrado');
        $payload = ['catalog_component_id' => $entry->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80];
        $store = route('academic-years.courses.components.store', [$course->academicYear, $course]);
        $this->post($store, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $component = $course->components()->sole();
        $update = route('academic-years.courses.components.update', [$course->academicYear, $course, $component]);
        $this->put($update, array_replace($payload, ['catalog_component_id' => $other->id]))->assertSessionHasNoErrors();
        $before = [$component->fresh()->getAttributes(), HistoryCurriculumComponent::count()];
        foreach (['Nome inventado', 'Nome <script>alert(1)</script>', ''] as $name) {
            $invalid = array_diff_key($payload, ['catalog_component_id' => true]) + ['name' => $name];
            $this->post($store, $invalid)->assertSessionHasErrors('catalog_component_id');
            $this->put($update, $invalid)->assertSessionHasErrors('catalog_component_id');
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
        foreach ([$otherStage, $incompatible, $deleted] as $unavailable) {
            $this->post($store, ['catalog_component_id' => $unavailable->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80])->assertSessionHasErrors('catalog_component_id');
        }
        HistoryCurriculumComponent::where('education_stage', 'fundamental')->delete();
        $page = $this->get(route('academic-years.courses.show', [$course->academicYear, $course]))->assertOk()
            ->assertSee('Não há componentes cadastrados no catálogo para esta etapa de ensino.')
            ->assertSee(route('history-curriculum.index'));
        $this->assertSame([], $this->names($page, 'new_component_name'));
        $this->assertSame(0, $course->components()->count());
    }

    public function test_an_inactive_area_can_be_retained_in_existing_matrices(): void
    {
        $course = $this->course();
        $entry = $this->entry('Componente já utilizado');
        $component = $course->components()->create(['name' => $entry->name, 'knowledge_area_id' => $entry->knowledge_area_id, 'workload_hours' => 80]);
        $entry->area->update(['active' => false]);
        $edit = $this->get(route('academic-years.courses.components.show', [$course->academicYear, $course, $component]))->assertOk();
        $this->assertSame([$entry->name], $this->names($edit, 'component_name'));
        $payload = ['catalog_component_id' => $entry->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 120];
        $this->post(route('academic-years.courses.components.store', [$course->academicYear, $course]), $payload)->assertSessionHasErrors('catalog_component_id');
        $this->put(route('academic-years.courses.components.update', [$course->academicYear, $course, $component]), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(120, $component->fresh()->workload_hours);
        $this->assertSame($entry->name, $component->fresh()->name);
    }

    public function test_selected_formation_survives_saving_and_syncing_even_when_the_area_has_another_default(): void
    {
        $course = $this->course('fundamental');
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $basic = $this->entry('Componente compartilhado', 'fundamental', area: $area);
        $diversified = HistoryCurriculumComponent::create(['name' => $basic->name, 'education_stage' => 'fundamental', 'formation' => 'Parte Diversificada', 'knowledge_area_id' => $area->id]);
        $count = HistoryCurriculumComponent::count();
        $payload = ['catalog_component_id' => $diversified->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80,
            'name' => 'Nome adulterado', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => 999999, 'education_stage' => 'medio'];
        $this->post(route('academic-years.courses.components.store', [$course->academicYear, $course]), $payload)->assertSessionHasNoErrors();
        $component = $course->components()->sole();
        $this->assertSame($basic->name, $component->name);
        $this->assertSame($area->id, $component->knowledge_area_id);
        $this->assertSame('Parte Diversificada', CurriculumCatalog::formationLabelForComponent($component));
        app(MatrixHistoryCatalog::class)->importAll();
        $this->assertSame($diversified->id, $component->historyCatalogEntries()->sole()->id);
        $this->assertSame($count, HistoryCurriculumComponent::count());
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante'])->id, 'school_id' => $course->academicYear->school_id, 'education_stage' => 'fundamental', 'title' => 'Histórico de teste']);
        $year = $history->years()->create(['label' => '1º Ano', 'source' => 'system']);
        $row = $history->components()->create(['name' => $diversified->name, 'knowledge_area' => $area->name, 'formation' => $diversified->formation]);
        $row->catalogEntries()->attach($diversified);
        $record = $row->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $component->id, 'score_label' => '8,0', 'workload_hours' => 80]);
        $before = $record->fresh()->getAttributes();
        $payload['catalog_component_id'] = $basic->id;
        $this->put(route('academic-years.courses.components.update', [$course->academicYear, $course, $component]), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Formação Geral Básica', $record->fresh()->component->formation);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertSame($basic->id, $component->historyCatalogEntries()->sole()->id);
    }

    public function test_imports_use_the_complete_catalog_definition_even_with_model_events_disabled(): void
    {
        $course = $this->course('fundamental');
        $entry = $this->entry('Componente importado', 'fundamental', area: KnowledgeArea::where('name', 'Linguagens')->firstOrFail());
        $entry->update(['formation' => 'Parte Complementar']);
        $command = app(ImportLegacyData::class);
        (new \ReflectionProperty($command, 'courseIdMap'))->setValue($command, ['test' => [1 => $course->id]]);
        Model::withoutEvents(fn () => (new \ReflectionMethod($command, 'importCurriculumComponents'))->invoke($command, 'test', [1 => $entry->knowledge_area_id], [['id' => 1, 'cursos_id' => 1, 'area_id' => 1, 'nome' => $entry->name, 'horas' => 80]]));
        $component = $course->components()->sole();
        $this->assertSame($entry->id, $component->historyCatalogEntries()->sole()->id);
        $this->assertSame('Parte Complementar', CurriculumCatalog::formationLabelForComponent($component));
    }

    public function test_imports_reject_unknown_or_ambiguous_classification_without_partial_matrix_writes(): void
    {
        $course = $this->course('fundamental');
        $entry = $this->entry('Componente importado', 'fundamental', area: KnowledgeArea::where('name', 'Linguagens')->firstOrFail());
        $command = app(ImportLegacyData::class);
        (new \ReflectionProperty($command, 'courseIdMap'))->setValue($command, ['test' => [1 => $course->id]]);
        $run = fn (array $rows) => DB::transaction(fn () => Model::withoutEvents(fn () => (new \ReflectionMethod($command, 'importCurriculumComponents'))->invoke($command, 'test', [1 => $entry->knowledge_area_id], $rows)));
        $good = ['id' => 1, 'cursos_id' => 1, 'area_id' => 1, 'nome' => $entry->name, 'horas' => 80];
        try {
            $run([$good, array_replace($good, ['id' => 2, 'nome' => 'Sem cadastro'])]);
            $this->fail('A importação não pode inventar um cadastro.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('components', $exception->errors());
        }
        $this->assertSame(0, $course->components()->count());
        HistoryCurriculumComponent::create(['name' => $entry->name, 'education_stage' => 'fundamental', 'formation' => 'Parte Diversificada', 'knowledge_area_id' => $entry->knowledge_area_id]);
        try {
            $run([$good]);
            $this->fail('A importação não pode escolher uma formação por aproximação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('components', $exception->errors());
        }
        $this->assertSame(0, $course->components()->count());
    }
}
