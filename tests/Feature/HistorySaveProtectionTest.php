<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryYear;
use App\Models\User;
use App\Support\HistoryCatalogIntegrator;
use App\Support\MatrixHistoryCatalog;
use App\Support\StudentHistoryEditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorySaveProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function history(): StudentAcademicHistory
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa de teste', 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Responsável', 'address' => 'Rua de teste', 'city' => 'Poxoréu',
            'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now(),
        ]);
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $person->id]));
        $student = Person::query()->create(['full_name' => 'Estudante de teste']);
        $school = School::query()->create(['name' => 'Escola de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'active' => true]);
        $history = StudentAcademicHistory::query()->create([
            'person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico escolar',
            'stage' => 'Ensino Fundamental', 'education_stage' => 'fundamental', 'is_unified' => true, 'active' => true,
        ]);
        $year = $history->years()->create($this->year(1) + ['position' => 1]);
        $component = $history->components()->create(['name' => 'Matemática', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Matemática', 'position' => 1]);
        $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0']);

        return $history;
    }

    private function year(int $grade): array
    {
        return [
            'source' => 'manual', 'label' => $grade.'º Ano', 'year' => (string) (2017 + $grade), 'stage' => 'Ensino Fundamental',
            'modality' => 'Regular', 'grade_phase' => $grade.'º Ano', 'grade_number' => $grade,
            'school_name' => 'Escola de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'country' => 'Brasil',
            'transcript_mode' => 'detailed', 'final_result' => 'Aprovado', 'workload_hours' => 800,
        ];
    }

    private function version(StudentAcademicHistory $history): string
    {
        $response = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk();
        preg_match('/name="history_version" value="([a-f0-9]{64})"/', $response->getContent(), $matches);
        $this->assertCount(2, $matches);

        return $matches[1];
    }

    private function payload(StudentAcademicHistory $history, array $years, ?string $version = null): array
    {
        return [
            'history_version' => $version ?? $this->version($history), 'history_payload_complete' => '1',
            'school_id' => $history->school_id, 'title' => $history->title, 'stage' => $history->stage, 'active' => 1,
            'years' => $years,
            'components' => [[
                'name' => 'Matemática', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Matemática',
                'records' => array_map(fn () => ['score_label' => '8,0'], $years),
            ]],
        ];
    }

    private function url(StudentAcademicHistory $history): string
    {
        return route('people.histories.update', [$history->person_id, $history]);
    }

    public function test_histories_reject_areas_from_the_other_stage_even_when_previously_imported(): void
    {
        foreach (['fundamental', 'medio'] as $stage) {
            foreach ([true, false] as $unified) {
                $history = $this->history();
                $stageLabel = $stage === 'fundamental' ? 'Ensino Fundamental' : 'Ensino Médio';
                $history->update(['education_stage' => $unified ? $stage : null, 'stage' => $stageLabel, 'is_unified' => $unified]);
                $year = $history->years()->sole();
                $year->update(['stage' => $stageLabel, 'source' => 'external']);
                $wrongArea = $stage === 'fundamental' ? 'Matemática e suas Tecnologias' : 'Matemática';
                $component = $history->components()->sole();
                $component->update(['knowledge_area' => $wrongArea]);
                $payload = $this->payload($history->fresh(), [$this->year(1) + ['id' => $year->id]], app(StudentHistoryEditGuard::class)->version($history->fresh()));
                $payload['years'][0]['stage'] = $stageLabel;
                $payload['components'][0]['knowledge_area'] = $wrongArea;
                $this->put($this->url($history), $payload)->assertSessionHasErrors('components.0.knowledge_area');
                $this->assertDatabaseHas('student_academic_history_records', ['student_academic_history_component_id' => $component->id, 'score_label' => '8,0']);
            }
        }
    }

    public function test_history_editor_keeps_years_collapsed_and_each_year_has_named_field_groups(): void
    {
        $history = $this->history();
        $history->years()->create($this->year(2) + ['position' => 2]);
        $response = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//details[@data-history-year]');
        $this->assertSame(2, $cards->length);
        $this->assertSame(0, $xpath->query('//details[@data-history-year and @open]')->length);
        foreach ($cards as $index => $card) {
            $this->assertSame(5, $xpath->query('.//fieldset/legend', $card)->length);
            $this->assertSame(1, $xpath->query('.//table[@data-sge-client-pagination]', $card)->length);
            $this->assertSame(1, $xpath->query('.//input[@name="years['.$index.'][year]"]', $card)->length);
            $this->assertSame(1, $xpath->query('.//input[@name="years['.$index.'][school_name]"]', $card)->length);
            $this->assertSame(1, $xpath->query('.//input[@name="years['.$index.'][workload_hours]"]', $card)->length);
        }
        $response->assertSee('Anos cursados')->assertSee('Notas e componentes')->assertDontSee('1. Anos cursados')->assertDontSee('2. Notas e componentes');
    }

    public function test_component_without_grade_survives_saving_and_reopening_a_global_three_four_year(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $years = [$this->year(1) + ['id' => $first->id]];
        foreach ([2, 3, 4] as $grade) {
            $data = $this->year($grade);
            if ($grade === 4) {
                $data = array_merge($data, ['label' => '3º/4º Ano', 'grade_phase' => '3º/4º Ano', 'transcript_mode' => 'summary']);
            }
            $saved = $history->years()->create($data + ['position' => $grade]);
            $years[] = $data + ['id' => $saved->id];
        }
        $payload = $this->yearlyPayload($history, $years, [
            0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0'])],
            3 => [$this->yearComponentData('Língua Inglesa', 'Linguagens')],
        ]);
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()
            ->assertSee('name="year_components[3][0][name]"', false)->assertSee('Língua Inglesa');
        $english = $history->components()->where('name', 'Língua Inglesa')->sole();
        $record = $english->records()->sole();
        $this->assertSame($years[3]['id'], $record->student_academic_history_year_id);
        $this->assertNull($record->score_label);
        $this->assertNull($record->score_numeric);
        $this->assertNull($record->workload_hours);
        $this->assertSame('8,0', $history->components()->where('name', 'Matemática')->sole()->records()->sole()->score_label);
        $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()->assertSee('Língua Inglesa');
        $this->assertDatabaseHas('student_academic_history_records', ['id' => $record->id]);
    }

    public function test_same_component_has_independent_grades_and_removal_in_one_year_preserves_the_other(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $second = $history->years()->create($this->year(2) + ['position' => 2]);
        $years = [$this->year(1) + ['id' => $first->id], $this->year(2) + ['id' => $second->id]];
        $this->put($this->url($history), $this->yearlyPayload($history, $years, [
            0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '0', 'absences' => 0])],
            1 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '7,5', 'workload_hours' => 80])],
        ]))->assertSessionHasNoErrors();
        $component = $history->components()->sole();
        $this->assertSame('0', $component->records()->where('student_academic_history_year_id', $first->id)->sole()->score_label);
        $this->assertSame('7,5', $component->records()->where('student_academic_history_year_id', $second->id)->sole()->score_label);
        $this->put($this->url($history), $this->yearlyPayload($history, $years, [
            0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '0', 'absences' => 0])],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $history->components()->sole()->records()->count());
        $this->assertSame($first->id, $history->components()->sole()->records()->sole()->student_academic_history_year_id);
        $this->assertSame(2, $history->years()->count());
    }

    public function test_yearly_component_errors_point_to_the_correct_year_and_conflicts_cannot_overwrite_grades(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $years = [$this->year(1) + ['id' => $year->id]];
        $this->put($this->url($history), $this->yearlyPayload($history, $years, [
            0 => [$this->yearComponentData('Matemática', 'Matemática e suas Tecnologias')],
        ]))->assertSessionHasErrors('year_components.0.0.knowledge_area');
        $this->put($this->url($history), $this->yearlyPayload($history, $years, [
            0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0']), $this->yearComponentData('Matemática', 'Matemática', ['score_label' => '6,0'])],
        ]))->assertSessionHasErrors('year_components.0.1.record.score_label');
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
        $this->put($this->url($history), $this->yearlyPayload($history, $years, [
            9 => [$this->yearComponentData('Língua Inglesa', 'Linguagens')],
        ]))->assertSessionHasErrors('year_components');
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
    }

    public function test_yearly_component_preserves_distinct_formations_and_existing_complementary_fields(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $rows = [$this->yearComponentData('Língua Inglesa', 'Linguagens', ['score_label' => '8,0', 'completed_workload_hours' => 72])];
        HistoryCurriculumComponent::create(['education_stage' => 'fundamental', 'formation' => 'Parte Diversificada', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id'), 'name' => 'Língua Inglesa', 'active' => true]);
        $rows[] = array_merge($this->yearComponentData('Língua Inglesa', 'Linguagens', ['score_label' => '7,0']), ['formation' => 'Parte Diversificada', 'module_label' => 'Módulo I', 'regulatory_reference' => 'Referência preservada']);
        $this->put($this->url($history), $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id]], [0 => $rows]))->assertSessionHasNoErrors();
        $this->assertSame(2, $history->components()->count());
        $this->assertSame('72.00', $history->components()->where('formation', 'Formação Geral Básica')->sole()->records()->sole()->completed_workload_hours);
        $this->assertSame('Módulo I', $history->components()->where('formation', 'Parte Diversificada')->sole()->module_label);
    }

    private function yearComponentData(string $name, string $area, array $record = []): array
    {
        return ['name' => $name, 'knowledge_area' => $area, 'formation' => 'Formação Geral Básica', 'record' => $record];
    }

    private function yearlyPayload(StudentAcademicHistory $history, array $years, array $rows): array
    {
        $payload = $this->payload($history, $years, app(StudentHistoryEditGuard::class)->version($history->fresh()));
        unset($payload['components']);

        return $payload + ['year_components_present' => '1', 'year_components' => $rows];
    }

    public function test_adding_an_external_year_keeps_a_component_registered_without_a_grade(): void
    {
        $history = $this->history();
        $payload = $this->yearlyPayload($history, [array_merge($this->year(4), ['transcript_mode' => 'summary'])], [0 => [$this->yearComponentData('Língua Inglesa', 'Linguagens')]]);
        $this->post(route('people.histories.store', $history->person_id), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $year = $history->years()->where('grade_number', 4)->sole();
        $this->assertSame('Língua Inglesa', $year->records()->with('component')->sole()->component->name);
        $this->assertNull($year->records()->sole()->score_label);
        $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()->assertSee('Língua Inglesa');
    }

    public function test_global_year_shows_components_without_individual_grades_and_preserves_legacy_submissions(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $year->update(['transcript_mode' => 'summary']);
        $response = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//fieldset[@data-history-components and not(@hidden) and not(@disabled)]')->length);
        $this->assertSame(1, $xpath->query('//td[@data-history-record-fields and @hidden]')->length);
        $this->assertSame(3, $xpath->query('//button[@data-add-history-component and @data-formation]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="year_components[0][0][record][score_label]" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@data-history-formation-totals and not(@hidden)]')->length);
        $this->assertSame(1, $xpath->query('//input[@data-formation-hours and @value="800"]')->length);
        $payload = $this->yearlyPayload($history, [array_merge($this->year(1), ['id' => $year->id, 'transcript_mode' => 'summary',
            'formation_workload_hours' => ['Formação Geral Básica' => '800', 'Parte Complementar' => '80', 'Parte Diversificada' => '120'],
            'enabled_formations' => ['Parte Complementar', 'Parte Diversificada'], 'workload_hours' => '999'])], [0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '99'])]]);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $year = $year->fresh();
        $this->assertSame('1000.00', $year->workload_hours);
        $this->assertEquals(['Formação Geral Básica' => 800, 'Parte Complementar' => 80, 'Parte Diversificada' => 120], $year->formation_workload_hours);
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
        $this->assertSame('Matemática', $history->components()->sole()->catalogEntries()->sole()->name);
        $totals = $history->fresh()->formationWorkloadTotals();
        $this->assertSame(['planned' => 800.0, 'completed' => 800.0], $totals['Formação Geral Básica']);
        $this->assertSame(['planned' => 80.0, 'completed' => 80.0], $totals['Parte Complementar']);
        $this->assertSame(['planned' => 120.0, 'completed' => 120.0], $totals['Parte Diversificada']);
    }

    public function test_global_totals_require_each_enabled_formation_and_do_not_multiply_component_hours(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $year->update(['transcript_mode' => 'summary']);
        $history->components()->sole()->records()->update(['workload_hours' => 800]);
        $component = $history->components()->create(['name' => 'Língua Portuguesa', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens']);
        $component->records()->create(['student_academic_history_year_id' => $year->id, 'workload_hours' => 800, 'score_label' => 'APROV']);
        $this->assertSame(800.0, $history->fresh()->formationWorkloadTotals()['Formação Geral Básica']['planned']);
        $payload = $this->yearlyPayload($history, [array_merge($this->year(1), ['id' => $year->id, 'transcript_mode' => 'summary', 'enabled_formations' => ['Parte Complementar'], 'formation_workload_hours' => ['Formação Geral Básica' => 800, 'Parte Complementar' => '']])], []);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasErrors('years.0.formation_workload_hours.Parte Complementar');
        $this->assertNull($year->fresh()->formation_workload_hours);
        $this->assertSame('800.00', $year->fresh()->workload_hours);
        $payload['years'][0]['formation_workload_hours']['Parte Complementar'] = 0;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $history->components()->count());
        $this->assertEquals(0, $year->fresh()->formation_workload_hours['Parte Complementar']);
    }

    public function test_global_optional_formations_are_independent_and_switching_back_preserves_grades(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $year->update(['transcript_mode' => 'summary', 'workload_hours' => 1000, 'formation_workload_hours' => ['Formação Geral Básica' => 800, 'Parte Complementar' => 80, 'Parte Diversificada' => 120]]);
        $payload = $this->yearlyPayload($history, [array_merge($this->year(1), ['id' => $year->id, 'transcript_mode' => 'summary', 'enabled_formations' => ['Parte Diversificada'], 'formation_workload_hours' => ['Formação Geral Básica' => 800, 'Parte Diversificada' => 120]])], []);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame('920.00', $year->fresh()->workload_hours);
        $this->assertEquals(['Formação Geral Básica' => 800, 'Parte Diversificada' => 120], $year->fresh()->formation_workload_hours);
        $this->assertArrayNotHasKey('Parte Complementar', $history->fresh()->formationWorkloadTotals());
        $payload['history_version'] = app(StudentHistoryEditGuard::class)->version($history->fresh());
        $payload['years'][0]['transcript_mode'] = 'detailed';
        $payload['years'][0]['workload_hours'] = 920;
        $payload['year_components'][0] = [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0'])];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
        $this->assertEquals(['Formação Geral Básica' => 800, 'Parte Diversificada' => 120], $year->fresh()->formation_workload_hours);
    }

    public function test_new_global_year_has_totals_without_individual_component_records(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $payload = $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id], array_merge($this->year(2), ['transcript_mode' => 'summary', 'enabled_formations' => ['Parte Complementar'], 'formation_workload_hours' => ['Formação Geral Básica' => '800', 'Parte Complementar' => '80']])], [0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0'])], 1 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '99'])]]);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $second = $history->years()->where('year', '2019')->sole();
        $this->assertSame('880.00', $second->workload_hours);
        $this->assertSame(0, $second->records()->count());
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
    }

    public function test_global_memberships_persist_by_formation_without_accepting_individual_grades(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        foreach (['Parte Complementar' => 'Expressão Corporal', 'Parte Diversificada' => 'Projeto de Leitura'] as $formation => $name) {
            HistoryCurriculumComponent::create(['education_stage' => 'fundamental', 'formation' => $formation, 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id'), 'name' => $name, 'active' => true]);
        }
        $globalYear = array_merge($this->year(2), ['transcript_mode' => 'summary', 'enabled_formations' => ['Parte Complementar', 'Parte Diversificada'], 'formation_workload_hours' => ['Formação Geral Básica' => 800, 'Parte Complementar' => 80, 'Parte Diversificada' => 120]]);
        $rows = [
            $this->yearComponentData('Matemática', 'Matemática', ['score_label' => '99', 'score_numeric' => 99, 'workload_hours' => 999, 'absences' => 200]),
            array_merge($this->yearComponentData('Expressão Corporal', 'Linguagens', ['score_label' => '99']), ['formation' => 'Parte Complementar']),
            array_merge($this->yearComponentData('Projeto de Leitura', 'Linguagens'), ['formation' => 'Parte Diversificada']),
        ];
        $payload = $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id], $globalYear], [0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0'])], 1 => $rows]);
        $payload += ['formation_totals_present' => 1, 'global_components_present' => 1];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $global = $history->years()->where('grade_number', 2)->sole();
        $this->assertSame(3, $global->records()->count());
        foreach ($global->records as $record) {
            $this->assertNull($record->score_label);
            $this->assertNull($record->score_numeric);
            $this->assertNull($record->workload_hours);
            $this->assertNull($record->absences);
        }
        $this->assertEquals(['Formação Geral Básica', 'Parte Complementar', 'Parte Diversificada'], $global->records->map(fn ($record) => $record->component->formation)->all());
        $this->assertSame('1000.00', $global->workload_hours);
        $this->assertSame('8,0', $year->records()->sole()->score_label);
        $this->assertSame(1600.0, $history->fresh()->formationWorkloadTotals()['Formação Geral Básica']['planned']);
        $html = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//input[starts-with(@name,"year_components[1]") and contains(@name,"[name]")]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="year_components[1][1][name]" and @value="Expressão Corporal"]')->length);
        // Reabrir e salvar não cria notas nem perde as três associações.
        $payload['history_version'] = $this->version($history->fresh());
        $payload['years'][1]['id'] = $global->id;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame(3, $global->records()->count());
    }

    public function test_global_memberships_can_be_added_and_removed_per_year_preserving_retained_records(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $second = $history->years()->create($this->year(2) + ['position' => 2]);
        $history->components()->sole()->records()->create(['student_academic_history_year_id' => $second->id, 'score_label' => '7,5', 'frequency_percentage' => 95]);
        $globalYear = fn ($grade, $id) => array_merge($this->year($grade), ['id' => $id, 'transcript_mode' => 'summary', 'formation_workload_hours' => ['Formação Geral Básica' => 800]]);
        $payload = $this->yearlyPayload($history, [$globalYear(1, $first->id), $globalYear(2, $second->id)], [0 => [$this->yearComponentData('Língua Portuguesa', 'Linguagens')], 1 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '99', 'frequency_percentage' => 10])]]);
        $payload += ['formation_totals_present' => 1, 'global_components_present' => 1];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Língua Portuguesa', $first->records()->with('component')->sole()->component->name);
        $this->assertNull($first->records()->sole()->score_label);
        $this->assertSame('7,5', $second->records()->sole()->score_label);
        $this->assertEquals(95, $second->records()->sole()->frequency_percentage);
        $payload['history_version'] = $this->version($history->fresh());
        $payload['years'][1]['transcript_mode'] = 'detailed';
        $payload['year_components'][1][0]['record'] = ['score_label' => '7,5', 'frequency_percentage' => 95];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame('7,5', $second->records()->sole()->score_label);
    }

    public function test_global_memberships_reject_disabled_formations_and_allow_explicit_removal(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $globalYear = array_merge($this->year(1), ['id' => $year->id, 'transcript_mode' => 'summary', 'formation_workload_hours' => ['Formação Geral Básica' => 800]]);
        $payload = $this->yearlyPayload($history, [$globalYear], [0 => [array_merge($this->yearComponentData('Língua Portuguesa', 'Linguagens'), ['formation' => 'Parte Complementar'])]]);
        $payload += ['formation_totals_present' => 1, 'global_components_present' => 1];
        $this->put($this->url($history), $payload)->assertSessionHasErrors('year_components.0.0.formation');
        $this->assertSame('8,0', $year->records()->sole()->score_label);
        $payload['year_components'] = [];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $year->records()->count());
        $this->assertSame('800.00', $year->fresh()->workload_hours);
    }

    public function test_global_memberships_reject_malformed_years_and_rows_without_changing_saved_records(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $payload = $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id]], []);
        $payload += ['formation_totals_present' => 1, 'global_components_present' => 1];
        foreach ([['years' => ['invalid']], ['year_components' => 'invalid'], ['year_components' => [[['formation' => ['invalid']]]]]] as $malformed) {
            $this->put($this->url($history), array_replace($payload, $malformed))->assertSessionHasErrors();
            $this->assertSame('8,0', $year->records()->sole()->score_label);
        }
    }

    public function test_history_area_and_component_choices_keep_legacy_values_in_alphabetical_position(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $old = $history->components()->sole();
        $old->update(['name' => 'Ábaco', 'knowledge_area' => 'Álgebra']);
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        foreach (['Zoologia', 'Biologia'] as $name) {
            HistoryCurriculumComponent::create(['education_stage' => 'fundamental', 'formation' => 'Formação Geral Básica', 'knowledge_area_id' => $area->id, 'name' => $name, 'active' => true]);
        }
        $legacy = $history->components()->create(['name' => 'Água', 'formation' => 'Formação Geral Básica', 'knowledge_area' => $area->name]);
        $legacy->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => 'AP']);
        $response = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//select[@name="year_components[0][0][knowledge_area]"]/option[@value!=""]') as $option) {
            $values[] = $option->getAttribute('value');
        }
        $this->assertSame('Álgebra', $values[0]);
        $this->assertSame(1, $xpath->query('//select[@name="year_components[0][0][knowledge_area]"]/option[@value="Álgebra" and @selected]')->length);
        $names = [];
        foreach ($xpath->query('//select[@data-history-component-select]/option[@value!=""]') as $option) {
            if (in_array($option->getAttribute('value'), ['Água', 'Biologia', 'Zoologia'], true)) {
                $names[] = $option->getAttribute('value');
            }
        }
        $this->assertSame(['Água', 'Biologia', 'Zoologia'], $names);
        $this->assertSame('Ábaco', $old->fresh()->name);
        $this->assertSame('8,0', $old->records()->sole()->score_label);
    }

    public function test_new_history_choices_use_active_catalog_entries_and_retain_existing_legacy_values(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $catalog = HistoryCurriculumComponent::create(['education_stage' => 'fundamental', 'formation' => 'Parte Complementar', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens')->value('id'), 'name' => 'Expressão Corporal', 'active' => true]);
        $html = $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $config = json_decode($document->getElementById('history-editor-config')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertContains(['formation' => 'Parte Complementar', 'area' => 'Linguagens', 'name' => 'Expressão Corporal'], $config['catalogs']['fundamental']);
        $payload = $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id]], [0 => [array_merge($this->yearComponentData('Expressão Corporal', 'Linguagens', ['score_label' => '8,0']), ['formation' => 'Parte Complementar'])]]);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $catalog->update(['active' => false]);
        $payload['history_version'] = app(StudentHistoryEditGuard::class)->version($history->fresh());
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $second = $history->years()->create($this->year(2) + ['position' => 2]);
        $payload['history_version'] = app(StudentHistoryEditGuard::class)->version($history->fresh());
        $payload['years'][] = $this->year(2) + ['id' => $second->id];
        $payload['year_components'][1] = $payload['year_components'][0];
        $this->put($this->url($history), $payload)->assertSessionHasErrors('year_components.1.0.name');
    }

    public function test_saving_after_a_catalog_rename_preserves_its_link_without_recreating_the_old_name(): void
    {
        $history = $this->history();
        $year = $history->years()->sole();
        $payload = $this->yearlyPayload($history, [$this->year(1) + ['id' => $year->id]], [0 => [$this->yearComponentData('Matemática', 'Matemática', ['score_label' => '8,0'])]]);
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $entry = $history->components()->sole()->catalogEntries()->sole();
        $entry->update(['name' => 'Matemática Básica']);
        $count = HistoryCurriculumComponent::count();
        $payload['history_version'] = app(StudentHistoryEditGuard::class)->version($history->fresh());
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertSame($count, HistoryCurriculumComponent::count());
        $this->assertSame($entry->id, $history->components()->sole()->catalogEntries()->sole()->id);
        $this->assertSame('8,0', $history->components()->sole()->records()->sole()->score_label);
    }

    public function test_a_merged_manual_history_keeps_the_matrix_classification_when_edited_again(): void
    {
        $history = $this->history();
        app(HistoryCatalogIntegrator::class)->syncHistory($history);
        $source = $history->components()->sole()->catalogEntries()->sole();
        $year = AcademicYear::create(['school_id' => $history->school_id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz Médio', 'stage' => 'medio']);
        $matrix = $course->components()->create(['name' => 'Lin. Inglesa', 'knowledge_area_id' => KnowledgeArea::where('name', 'Linguagens e suas Tecnologias')->value('id'), 'active' => true]);
        $target = app(MatrixHistoryCatalog::class)->syncComponent($matrix);
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $this->get(route('people.histories.edit', [$history->person_id, $history]))->assertOk()->assertSee('value="Linguagens e suas Tecnologias" selected', false);
        $external = $history->years()->sole();
        $payload = $this->yearlyPayload($history->fresh(), [$this->year(1) + ['id' => $external->id]], [0 => [$this->yearComponentData('Lin. Inglesa', 'Linguagens e suas Tecnologias', ['score_label' => '8,5'])]]);
        $payload['formation_totals_present'] = 1;
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $component = $history->components()->sole();
        $this->assertSame($target->id, $component->catalogEntries()->sole()->id);
        $this->assertSame('Linguagens e suas Tecnologias', $component->knowledge_area);
        $this->assertSame('8,5', $component->records()->sole()->score_label);
        $this->assertSame('Lin. Inglesa', $matrix->fresh()->name);
    }

    public function test_new_year_is_added_without_replacing_existing_year_or_its_grade(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $this->put($this->url($history), $this->payload($history, [
            $this->year(1) + ['id' => $first->id], $this->year(2),
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $first->id, 'year' => '2018']);
        $this->assertSame(2, $history->years()->count());
        $this->assertDatabaseHas('student_academic_history_records', ['student_academic_history_year_id' => $first->id, 'score_label' => '8,0']);
        $second = $history->years()->where('year', '2019')->sole();
        $this->put($this->url($history), $this->payload($history, [
            $this->year(1) + ['id' => $first->id], $this->year(2) + ['id' => $second->id], $this->year(3),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['2018', '2019', '2020'], $history->years()->pluck('year')->all());
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $second->id]);
    }

    public function test_stale_tab_cannot_remove_a_year_added_by_another_save(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $oldVersion = $this->version($history);
        $firstData = $this->year(1) + ['id' => $first->id];
        $this->put($this->url($history), $this->payload($history, [$firstData, $this->year(2)], $oldVersion))->assertSessionHasNoErrors();
        $ids = $history->years()->pluck('id')->all();
        $this->put($this->url($history), $this->payload($history, [$firstData, $this->year(3)], $oldVersion))
            ->assertSessionHasErrors('history_version');
        $this->assertSame($ids, $history->years()->pluck('id')->all());
        $this->assertSame(['2018', '2019'], $history->years()->pluck('year')->all());
        $this->assertSame(2, $history->components()->first()->records()->count());
    }

    public function test_old_form_without_version_cannot_change_the_history(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $payload = $this->payload($history, [$this->year(2)]);
        unset($payload['history_version']);
        $this->put($this->url($history), $payload)->assertSessionHasErrors('history_version');
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $first->id, 'year' => '2018']);
    }

    public function test_truncated_submission_cannot_replace_saved_data(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $payload = $this->payload($history, [$this->year(1) + ['id' => $first->id]]);
        unset($payload['history_payload_complete']);
        $this->put($this->url($history), $payload)->assertSessionHasErrors('history_payload_complete');
        $this->assertDatabaseHas('student_academic_history_records', ['student_academic_history_year_id' => $first->id, 'score_label' => '8,0']);
    }

    public function test_fresh_submission_cannot_silently_omit_an_existing_year(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $this->put($this->url($history), $this->payload($history, [$this->year(2)]))->assertSessionHasErrors('years');
        $this->assertSame([$first->id], $history->years()->pluck('id')->all());
    }

    public function test_explicit_removal_is_scoped_and_audited_without_deleting_other_years(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $second = $history->years()->create($this->year(2) + ['position' => 2]);
        $payload = $this->payload($history, [$this->year(2) + ['id' => $second->id]]);
        $payload['removed_year_ids'] = [$first->id];
        $this->put($this->url($history), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('student_academic_history_years', ['id' => $first->id]);
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $second->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => StudentAcademicHistoryYear::class, 'auditable_id' => $first->id, 'action' => 'deleted']);
        $this->assertSame('2018', AuditLog::query()->where('auditable_type', StudentAcademicHistoryYear::class)->where('auditable_id', $first->id)->where('action', 'deleted')->sole()->old_values['year']);
    }

    public function test_another_history_year_cannot_be_attached_or_removed(): void
    {
        $history = $this->history();
        $otherPerson = Person::query()->create(['full_name' => 'Outro estudante']);
        $other = StudentAcademicHistory::query()->create(array_merge($history->getAttributes(), ['person_id' => $otherPerson->id]));
        $foreign = $other->years()->create($this->year(2) + ['position' => 1]);
        $first = $history->years()->sole();
        $payload = $this->payload($history, [$this->year(1) + ['id' => $first->id]]);
        $payload['removed_year_ids'] = [$foreign->id];
        $this->put($this->url($history), $payload)->assertSessionHasErrors('years');
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $foreign->id]);
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $first->id]);
    }

    public function test_system_year_refresh_does_not_invalidate_manual_edit_version(): void
    {
        $history = $this->history();
        $history->update(['is_unified' => true]);
        $system = $history->years()->create($this->year(9) + ['position' => 2]);
        $system->update(['source' => 'system']);
        $version = app(StudentHistoryEditGuard::class)->version($history->fresh());
        $system->update(['workload_hours' => 900]);
        $this->assertSame($version, app(StudentHistoryEditGuard::class)->version($history->fresh()));
    }

    public function test_stale_form_cannot_overwrite_a_grade_even_when_year_count_is_unchanged(): void
    {
        $history = $this->history();
        $first = $history->years()->sole();
        $version = $this->version($history);
        $record = $history->components()->first()->records()->sole();
        $record->update(['score_label' => '9,0']);
        $this->put($this->url($history), $this->payload($history, [$this->year(1) + ['id' => $first->id]], $version))
            ->assertSessionHasErrors('history_version');
        $this->assertDatabaseHas('student_academic_history_records', ['id' => $record->id, 'score_label' => '9,0']);
    }
}
