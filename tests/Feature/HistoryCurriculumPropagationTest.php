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
use App\Support\HistoryCurriculumPropagation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class HistoryCurriculumPropagationTest extends TestCase
{
    use RefreshDatabase;

    private function area(string $name): KnowledgeArea
    {
        return KnowledgeArea::create(['name' => $name, 'formation' => 'Itinerário Formativo', 'active' => true]);
    }

    private function matrix(KnowledgeArea $area, string $name = 'Componente', string $stage = 'tecnico'): CurriculumComponent
    {
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025, 'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31']);
        $course = $year->courses()->create(['name' => 'Matriz de teste', 'stage' => $stage]);

        return $course->components()->create(['name' => $name, 'knowledge_area_id' => $area->id, 'workload_hours' => 80]);
    }

    private function history(HistoryCurriculumComponent $entry, string $source = 'system', string $stage = 'medio', ?int $matrixId = null): array
    {
        $history = StudentAcademicHistory::create(['person_id' => Person::create(['full_name' => 'Estudante de teste'])->id, 'school_id' => School::create(['name' => 'Escola de origem'])->id, 'title' => 'Histórico', 'is_unified' => true, 'education_stage' => $stage]);
        $year = $history->years()->create(['label' => '1º Ano', 'grade_phase' => '1º Ano', 'stage' => $stage === 'medio' ? 'Ensino Médio' : 'Ensino Técnico Profissionalizante', 'source' => $source, 'year' => '2025']);
        $component = $history->components()->create(['name' => $entry->name, 'knowledge_area' => $entry->area->name, 'formation' => $stage === 'medio' ? 'Itinerário Formativo' : 'Formação Técnica Profissional']);
        $component->catalogEntries()->attach($entry);
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $matrixId, 'score_label' => '8,5', 'score_numeric' => 8.5, 'workload_hours' => 80, 'completed_workload_hours' => 75, 'absences' => 5, 'frequency_percentage' => 93.75, 'result' => 'Aprovado']);

        return [$history, $component, $record];
    }

    private function values($record): array
    {
        return Arr::only($record->fresh()->getAttributes(), ['id', 'student_academic_history_year_id', 'curriculum_component_id', 'score_label', 'score_numeric', 'workload_hours', 'completed_workload_hours', 'frequency_percentage', 'absences', 'result', 'created_at', 'updated_at']);
    }

    public function test_editing_a_matrix_component_immediately_updates_only_its_records_and_preserves_results(): void
    {
        $old = $this->area('Área anterior');
        $new = $this->area('Técnico em Móveis');
        $one = $this->matrix($old);
        $two = $this->matrix($old);
        $entry = $one->historyCatalogEntries()->sole();
        [$history, $component, $first] = $this->history($entry, matrixId: $one->id);
        $year = $history->years()->create(['label' => '2º Ano', 'source' => 'system', 'stage' => 'Ensino Médio', 'year' => '2026']);
        $second = $component->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $two->id, 'score_label' => '9,0', 'workload_hours' => 90, 'absences' => 2]);
        $before = [$this->values($first), $this->values($second), $two->fresh()->getAttributes()];
        $one->update(['knowledge_area_id' => $new->id, 'name' => 'Nome atualizado']);
        $this->assertSame('Técnico em Móveis', $first->fresh()->component->knowledge_area);
        $this->assertSame('Nome atualizado', $first->fresh()->component->name);
        $this->assertSame('Área Anterior', $second->fresh()->component->knowledge_area);
        $this->assertNotSame($first->fresh()->student_academic_history_component_id, $second->fresh()->student_academic_history_component_id);
        $this->assertSame($before, [$this->values($first), $this->values($second), $two->fresh()->getAttributes()]);
        $this->assertSame('medio', $history->fresh()->education_stage);
        $this->assertSame($one->historyCatalogEntries()->sole()->id, $first->fresh()->component->catalogEntries()->sole()->id);
        $one->update(['name' => 'Outro nome']);
        $this->assertSame('Outro nome', $first->fresh()->component->name);
        $this->assertSame('Componente', $second->fresh()->component->name);
    }

    public function test_equivalent_classifications_rejoin_without_duplicating_or_recreating_year_records(): void
    {
        $old = $this->area('Área anterior');
        $new = $this->area('Área atual');
        $one = $this->matrix($old);
        $two = $this->matrix($old);
        [$history, $component, $first] = $this->history($one->historyCatalogEntries()->sole(), matrixId: $one->id);
        $year = $history->years()->create(['label' => '2º Ano', 'source' => 'system', 'stage' => 'Ensino Médio']);
        $second = $component->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $two->id, 'score_label' => '9,0']);
        $before = [$this->values($first), $this->values($second)];
        $one->update(['knowledge_area_id' => $new->id]);
        $this->assertSame(2, $history->components()->count());
        $two->update(['knowledge_area_id' => $new->id]);
        $this->assertSame(1, $history->components()->count());
        $this->assertSame($first->fresh()->student_academic_history_component_id, $second->fresh()->student_academic_history_component_id);
        $this->assertSame($before, [$this->values($first), $this->values($second)]);
    }

    public function test_area_rename_and_formation_changes_propagate_without_turning_high_school_into_technical_formation(): void
    {
        $area = $this->area('Área anterior');
        $matrix = $this->matrix($area);
        $entry = $matrix->historyCatalogEntries()->sole();
        [, $medium, $record] = $this->history($entry, matrixId: $matrix->id);
        [, $technical, $technicalRecord] = $this->history($entry, stage: 'tecnico', matrixId: $matrix->id);
        $before = [$this->values($record), $this->values($technicalRecord)];
        $area->update(['name' => 'Área nova', 'formation' => 'Parte Complementar']);
        $this->assertSame('Área Nova', $medium->fresh()->knowledge_area);
        $this->assertSame('Parte Complementar', $medium->fresh()->formation);
        $this->assertSame('Formação Técnica Profissional', $technical->fresh()->formation);
        $this->assertSame('Área Nova', $technical->fresh()->knowledge_area);
        $this->assertSame($before, [$this->values($record), $this->values($technicalRecord)]);
    }

    public function test_catalog_edit_propagates_to_manual_years_without_changing_another_stage(): void
    {
        $area = $this->area('Área anterior');
        $entry = HistoryCurriculumComponent::create(['name' => 'Nome antigo', 'education_stage' => 'medio', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => $area->id, 'active' => true]);
        [$history, $component, $record] = $this->history($entry, 'manual');
        $other = HistoryCurriculumComponent::create(['name' => 'Nome antigo', 'education_stage' => 'fundamental', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => $area->id, 'active' => true]);
        $component->catalogEntries()->attach($other);
        $year = $history->years()->create(['label' => '9º Ano', 'source' => 'manual', 'stage' => 'Ensino Fundamental']);
        $unaffected = $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '7,0']);
        $before = [$this->values($record), $this->values($unaffected)];
        $entry->update(['name' => 'Novo nome']);
        $this->assertSame('Novo nome', $record->fresh()->component->name);
        $this->assertSame('Nome antigo', $unaffected->fresh()->component->name);
        $this->assertSame($before, [$this->values($record), $this->values($unaffected)]);
        $this->assertSame($other->id, $unaffected->fresh()->component->catalogEntries()->sole()->id);
    }

    public function test_audited_past_area_moves_repair_all_63_history_components_and_release_the_old_area(): void
    {
        $old = $this->area('Educação Profissional e Tecnológica - Técnico em Móveis');
        $new = $this->area('Técnico em Móveis');
        $matrix = $this->matrix($old, 'Administração');
        $matrix->update(['knowledge_area_id' => $new->id]);
        $oldEntry = HistoryCurriculumComponent::create(['name' => 'Administração', 'education_stage' => 'medio', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => $old->id, 'active' => true]);
        $oldEntry->forceFill(['matrix_origin' => true])->save();
        $records = collect();
        foreach (range(1, 63) as $number) {
            [, , $record] = $this->history($oldEntry);
            $records->push($record);
        }
        $before = $records->map(fn ($record) => $this->values($record))->all();
        $matrixBefore = $matrix->fresh()->getAttributes();
        $this->assertSame(63, app(HistoryCurriculumPropagation::class)->repairFromAudits());
        $this->assertSame(0, app(HistoryCurriculumPropagation::class)->repairFromAudits());
        foreach ($records as $record) {
            $this->assertSame('Técnico em Móveis', $record->fresh()->component->knowledge_area);
            $this->assertSame('Itinerário Formativo', $record->fresh()->component->formation);
        }
        $this->assertSame($before, $records->map(fn ($record) => $this->values($record))->all());
        $this->assertSame($matrixBefore, $matrix->fresh()->getAttributes());
        $this->assertSame(0, $old->fresh()->withUsageCounts()->findOrFail($old->id)->usageCount());
        $actor = Person::create(['full_name' => 'Administrador', 'cpf' => '12345678901', 'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Responsável', 'address' => 'Rua', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $actor->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $actor->id]))->delete(route('knowledge-areas.destroy', $old))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('knowledge_areas', ['id' => $old->id]);
        $this->assertSame($before, $records->map(fn ($record) => $this->values($record))->all());
    }

    public function test_legacy_technical_projection_gets_a_stable_origin_and_follows_later_edits(): void
    {
        $area = $this->area('Área técnica');
        $source = $this->matrix($area);
        $other = $this->matrix($area);
        [$history, , $record] = $this->history($source->historyCatalogEntries()->sole());
        $class = $source->course->academicYear->classes()->create(['name' => 'Turma técnica']);
        $enrollment = $class->enrollments()->create(['person_id' => $history->person_id, 'enrolled_at' => '2025-01-01', 'status' => 'matriculado', 'type' => 'regular']);
        $enrollment->courses()->attach($source->academic_course_id);
        $this->assertSame(1, app(HistoryCurriculumPropagation::class)->backfillMatrixOrigins());
        $this->assertSame($source->id, $record->fresh()->curriculum_component_id);
        $before = [$this->values($record), $other->fresh()->getAttributes()];
        $source->update(['name' => 'Nome revisado']);
        $this->assertSame('Nome revisado', $record->fresh()->component->name);
        $this->assertSame($before, [$this->values($record), $other->fresh()->getAttributes()]);
    }

    public function test_audit_repair_does_not_guess_between_conflicting_destinations(): void
    {
        $old = $this->area('Área anterior');
        $one = $this->matrix($old);
        $two = $this->matrix($old);
        $one->update(['knowledge_area_id' => $this->area('Destino um')->id]);
        $two->update(['knowledge_area_id' => $this->area('Destino dois')->id]);
        $entry = HistoryCurriculumComponent::create(['name' => 'Componente', 'education_stage' => 'medio', 'formation' => 'Itinerário Formativo', 'knowledge_area_id' => $old->id, 'active' => true]);
        $entry->forceFill(['matrix_origin' => true])->save();
        [, $component, $record] = $this->history($entry);
        $before = [$component->fresh()->getAttributes(), $this->values($record)];
        $this->assertSame(0, app(HistoryCurriculumPropagation::class)->repairFromAudits());
        $this->assertSame($before, [$component->fresh()->getAttributes(), $this->values($record)]);
    }
}
