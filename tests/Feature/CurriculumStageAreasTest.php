<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\CurriculumAreaStage;
use App\Support\CurriculumAreaStageReconciler;
use App\Support\CurriculumCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurriculumStageAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_separates_all_four_basic_areas_in_both_directions_and_preserves_custom_areas(): void
    {
        $custom = KnowledgeArea::create(['name' => 'Informática', 'formation' => CurriculumCatalog::FORMATION_COMPLEMENTARY]);
        foreach (['fundamental', 'medio'] as $stage) {
            $course = new AcademicCourse(['stage' => $stage]);
            $names = CurriculumCatalog::knowledgeAreasForCourse($course)->pluck('name');
            $expected = collect(config("curriculum.stages.$stage.formations.formacao_geral_basica.areas"))->pluck('name');
            foreach ($expected as $name) {
                $this->assertContains($name, $names);
            }
            $otherStage = $stage === 'fundamental' ? 'medio' : 'fundamental';
            foreach (config("curriculum.stages.$otherStage.formations.formacao_geral_basica.areas") as $area) {
                $this->assertNotContains($area['name'], $names);
            }
            $this->assertContains($custom->name, $names);
        }
        $this->assertSame('Ciências Humanas', CurriculumAreaStage::canonicalName('CIENCIAS HUMANAS E SOCIAIS APLICADAS', 'fundamental'));
        $this->assertSame('Linguagens', CurriculumAreaStage::canonicalName('Linguagens, Códigos e suas Tecnologias', 'fundamental'));
        $this->assertSame('Ciências Humanas e Sociais Aplicadas', CurriculumAreaStage::canonicalName('Ciências Humanas e suas Tecnologias', 'medio'));
        $this->assertTrue(CurriculumAreaStage::compatible('Área Técnica', 'tecnico'));
        $this->assertTrue(CurriculumAreaStage::compatible('Parte Diversificada', 'medio'));
        $this->assertTrue(CurriculumAreaStage::compatible('Parte Complementar', 'medio'));
        $this->assertFalse(CurriculumAreaStage::compatible('Área e suas Tecnologias', 'fundamental'));
    }

    public function test_create_and_edit_reject_the_opposite_stage_and_cannot_keep_a_preexisting_wrong_area(): void
    {
        $year = $this->yearAndManager();
        foreach (['fundamental', 'medio'] as $stage) {
            $course = $year->courses()->create(['name' => $stage, 'stage' => $stage]);
            $otherStage = $stage === 'fundamental' ? 'medio' : 'fundamental';
            foreach (array_slice(config("curriculum.stages.$otherStage.formations.formacao_geral_basica.areas"), 0, 4) as $area) {
                $wrong = KnowledgeArea::where('name', $area['name'])->firstOrFail();
                $payload = ['name' => 'Componente', 'knowledge_area_id' => $wrong->id, 'workload_mode' => 'workload_hours', 'workload_hours' => 80];
                $this->post(route('academic-years.courses.components.store', [$year, $course]), $payload)->assertSessionHasErrors('knowledge_area_id');
                $component = $course->components()->create(['name' => 'Componente existente', 'knowledge_area_id' => $wrong->id]);
                $response = $this->get(route('academic-years.courses.components.show', [$year, $course, $component]))->assertOk();
                $this->assertNotContains($wrong->id, $response->viewData('knowledgeAreas')->modelKeys());
                $this->put(route('academic-years.courses.components.update', [$year, $course, $component]), $payload)->assertSessionHasErrors('knowledge_area_id');
                $rightName = CurriculumAreaStage::canonicalName($wrong->name, $stage);
                $right = KnowledgeArea::where('name', $rightName)->firstOrFail();
                $payload['knowledge_area_id'] = $right->id;
                $this->put(route('academic-years.courses.components.update', [$year, $course, $component]), $payload)->assertSessionHasNoErrors();
                $this->assertSame($right->id, $component->fresh()->knowledge_area_id);
            }
        }
    }

    public function test_course_stage_changes_and_area_renames_cannot_create_incompatible_associations(): void
    {
        $year = $this->yearAndManager();
        $course = $year->courses()->create(['name' => 'Fundamental', 'stage' => 'fundamental']);
        $area = KnowledgeArea::where('name', 'Linguagens')->firstOrFail();
        $course->components()->create(['name' => 'Português', 'knowledge_area_id' => $area->id]);
        $this->put(route('academic-years.courses.update', [$year, $course]), [
            'name' => $course->name, 'stage' => 'medio', 'modality' => 'regular', 'class_hour_minutes' => 50,
        ])->assertSessionHasErrors('stage');
        $this->assertSame('fundamental', $course->fresh()->stage);
        $person = auth()->user()->person;
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(auth()->user()->fresh());
        $this->put(route('knowledge-areas.update', $area), ['name' => 'Linguagens e Tecnologias', 'formation' => CurriculumCatalog::FORMATION_FGB])->assertSessionHasErrors('name');
        $this->assertSame('Linguagens', $area->fresh()->name);
    }

    public function test_diagnosis_is_read_only_and_repair_is_idempotent_preserving_all_grades_hours_and_ids(): void
    {
        $year = $this->yearAndManager();
        foreach (['fundamental', 'medio'] as $stage) {
            $course = $year->courses()->create(['name' => $stage, 'stage' => $stage]);
            $otherStage = $stage === 'fundamental' ? 'medio' : 'fundamental';
            foreach (array_slice(config("curriculum.stages.$otherStage.formations.formacao_geral_basica.areas"), 0, 4) as $area) {
                $wrong = KnowledgeArea::where('name', $area['name'])->firstOrFail();
                $course->components()->create(['name' => 'Componente', 'knowledge_area_id' => $wrong->id, 'workload_hours' => 80]);
                $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id,
                    'school_id' => $year->school_id, 'education_stage' => $stage, 'title' => 'Histórico de teste']);
                $historyYear = $history->years()->create(['label' => '1º Ano', 'source' => 'external', 'transcript_mode' => 'detailed', 'final_result' => 'Aprovado']);
                $component = $history->components()->create(['name' => 'Componente', 'formation' => CurriculumCatalog::FORMATION_FGB, 'knowledge_area' => $wrong->name]);
                $component->records()->create(['student_academic_history_year_id' => $historyYear->id, 'score_label' => '8,5', 'workload_hours' => 80, 'absences' => 2]);
            }
        }
        $records = DB::table('student_academic_history_records')->orderBy('id')->get()->toJson();
        $catalog = KnowledgeArea::orderBy('id')->get()->toJson();
        $before = app(CurriculumAreaStageReconciler::class)->inspect();
        $this->assertSame(8, $before['curriculum_components']);
        $this->assertSame(8, $before['history_components']);
        $auditCount = DB::table('audit_logs')->count();
        $this->artisan('curriculum:reconcile-stage-areas')->assertSuccessful();
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame($before, app(CurriculumAreaStageReconciler::class)->inspect());
        $this->artisan('curriculum:reconcile-stage-areas --apply')->assertSuccessful();
        $remaining = app(CurriculumAreaStageReconciler::class)->inspect();
        $this->assertSame(0, $remaining['curriculum_components']);
        $this->assertSame(0, $remaining['history_components']);
        $this->assertSame($records, DB::table('student_academic_history_records')->orderBy('id')->get()->toJson());
        $this->assertSame($catalog, KnowledgeArea::orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('curriculum_components', 8);
        $this->assertDatabaseCount('student_academic_history_components', 8);
        $this->assertDatabaseCount('audit_logs', $auditCount + 16);
        $this->artisan('curriculum:reconcile-stage-areas --apply')->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', $auditCount + 16);
    }

    public function test_imported_area_is_mapped_by_stage_without_renaming_the_shared_area(): void
    {
        $area = KnowledgeArea::where('name', 'Linguagens e suas Tecnologias')->firstOrFail();
        $elementary = CurriculumAreaStage::areaForStage($area, 'fundamental');
        $this->assertSame('Linguagens', $elementary->name);
        $this->assertNotSame($area->id, $elementary->id);
        $this->assertSame($area->id, CurriculumAreaStage::areaForStage($area, 'medio')->id);
        $this->assertSame('Linguagens e suas Tecnologias', $area->fresh()->name);
        $this->assertSame($area->id, CurriculumAreaStage::areaForStage($elementary, 'medio')->id);
    }

    public function test_legacy_history_stage_is_resolved_from_years_without_guessing_a_mixed_stage(): void
    {
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante'])->id, 'title' => 'Histórico recebido']);
        $history->years()->create(['stage' => 'Ensino Fundamental', 'label' => '1º Ano']);
        $this->assertSame('fundamental', CurriculumAreaStage::historyStage($history->fresh()));
        $history->years()->create(['stage' => 'Ensino Médio', 'label' => '1º Ano']);
        $this->assertNull(CurriculumAreaStage::historyStage($history->fresh()));
    }

    public function test_mixed_history_uses_the_component_records_stage_and_does_not_guess_an_empty_history(): void
    {
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'title' => 'Histórico recebido']);
        $elementary = $history->years()->create(['label' => '9º Ano', 'stage' => 'Ensino Fundamental']);
        $history->years()->create(['label' => '1º Ano', 'stage' => 'Ensino Médio']);
        $component = $history->components()->create(['name' => 'Matemática', 'knowledge_area' => 'Matemática e suas Tecnologias']);
        $component->records()->create(['student_academic_history_year_id' => $elementary->id, 'score_label' => '8,0']);
        $empty = StudentAcademicHistory::create(['person_id' => $history->person_id, 'title' => 'Sem etapa identificada']);
        $unclassified = $empty->components()->create(['name' => 'Matemática', 'knowledge_area' => 'Matemática e suas Tecnologias']);
        $this->artisan('curriculum:reconcile-stage-areas --apply')->assertSuccessful();
        $this->assertSame('Matemática', $component->fresh()->knowledge_area);
        $this->assertSame('Matemática e Suas Tecnologias', $unclassified->fresh()->knowledge_area);
        $report = app(CurriculumAreaStageReconciler::class)->inspect();
        $this->assertSame(1, $report['unclassified_history_components']);
        $this->assertFalse($report['unclassified_groups'][0]['has_years']);
        $this->assertFalse($report['unclassified_groups'][0]['has_records']);
    }

    private function yearAndManager(): AcademicYear
    {
        $school = School::create(['name' => 'Escola de teste']);
        $person = Person::create(['full_name' => 'Gestora de teste', 'cpf' => '12345678901', 'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria',
            'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_MANAGER, 'school_id' => $school->id, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $person->id]));

        return AcademicYear::create(['name' => '2026', 'school_id' => $school->id, 'reference_year' => 2026, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
    }
}
