<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumComponentsTable;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\HistoryCatalogIntegrator;
use App\Support\MatrixHistoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    public function test_components_from_two_matrices_can_be_merged_but_direct_catalog_editing_and_deletion_stay_protected(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $first = $this->matrixComponent('Lin. Inglesa');
        $second = $this->matrixComponent('Língua Inglesa');
        $source = app(MatrixHistoryCatalog::class)->syncComponent($first);
        $target = app(MatrixHistoryCatalog::class)->syncComponent($second);
        $before = [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()];
        $this->get(route('history-curriculum.merge-form', $source))->assertOk()->assertSee('name="keep"', false)
            ->assertSee('data-confirm-message=', false)->assertSee('Os históricos e as matrizes vinculadas');
        $this->put(route('history-curriculum.update', $source), ['name' => 'Língua Inglesa'])->assertForbidden();
        $this->delete(route('history-curriculum.destroy', $source))->assertForbidden();
        Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', $source->name)
            ->assertSeeHtml(route('history-curriculum.merge-form', $source))->assertDontSeeHtml('id="catalog-edit-'.$source->id.'"');
        $this->assertSame($before, [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $first->id, 'history_curriculum_component_id' => $source->id]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $second->id, 'history_curriculum_component_id' => $target->id]);
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $this->assertSame($second->name, $first->fresh()->name);
        $this->assertSame($before[1], $second->fresh()->getAttributes());
        $this->assertSame(Arr::except($before[0], ['name', 'updated_at']), Arr::except($first->fresh()->getAttributes(), ['name', 'updated_at']));
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $first->id, 'history_curriculum_component_id' => $target->id]);
    }

    public function test_matrix_merge_updates_all_original_matrices_and_histories_without_changing_diaries_or_results(): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $one = $this->matrixComponent('Inglês antigo');
        $two = $this->matrixComponent('Inglês antigo');
        $two->update(['weekly_lessons' => 3, 'workload_hours' => null, 'notes' => 'Observação preservada']);
        $kept = $this->matrixComponent('Língua Inglesa', 'fundamental', 'Parte Complementar', 'Parte Complementar');
        $source = $one->historyCatalogEntries()->sole();
        $target = $kept->historyCatalogEntries()->sole();
        $class = $one->course->academicYear->classes()->create(['name' => 'Turma original']);
        $period = $one->course->academicYear->periods()->create(['name' => '1º Bimestre', 'position' => 1, 'starts_at' => '2026-01-01', 'ends_at' => '2026-03-31']);
        $assignment = SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $one->id, 'teacher_person_id' => $admin->person_id, 'active' => true]);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $one->id, 'academic_period_id' => $period->id, 'teacher_person_id' => $admin->person_id, 'title' => 'Avaliação', 'assessment_date' => '2026-02-02']);
        $attendance = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $one->id, 'academic_period_id' => $period->id, 'teacher_person_id' => $admin->person_id, 'class_date' => '2026-02-02', 'lesson_count' => 2]);
        $content = DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $one->id, 'academic_period_id' => $period->id, 'class_date' => '2026-02-02', 'content' => 'Conteúdo preservado', 'created_by_person_id' => $admin->person_id]);
        $history = $this->history();
        $year = $history->years()->create(['label' => '8º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'system']);
        $row = $history->components()->create(['name' => $source->name, 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $row->catalogEntries()->attach($source);
        $record = $row->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $one->id, 'score_label' => '8,0', 'score_numeric' => 8, 'workload_hours' => 80, 'completed_workload_hours' => 76, 'frequency_percentage' => 95, 'absences' => 4, 'result' => 'Aprovado']);
        $enrollment = $class->enrollments()->create(['person_id' => $history->person_id, 'enrolled_at' => '2026-01-01', 'status' => 'matriculado', 'type' => 'regular']);
        $grade = $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 8]);
        $presence = $attendance->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'falta', 'attended_lessons' => 0]);
        $models = collect([$kept, $one->course, $two->course, $assignment, $assessment, $attendance, $content, $record, $year, $grade, $presence]);
        $before = $models->map(fn ($model) => $model->fresh()->getAttributes())->all();
        $matrixValues = collect([$one, $two])->map(fn ($matrix) => Arr::except($matrix->fresh()->getAttributes(), ['name', 'knowledge_area_id', 'updated_at']))->all();
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id, 'keep' => 'target'])->assertSessionHasNoErrors();
        foreach ([$one, $two] as $matrix) {
            $this->assertSame($target->name, $matrix->fresh()->name);
            $this->assertSame($target->knowledge_area_id, $matrix->fresh()->knowledge_area_id);
            $this->assertSame($target->formation, $matrix->fresh()->area->formation);
            $this->assertSame($target->id, $matrix->historyCatalogEntries()->sole()->id);
        }
        $this->assertSame($matrixValues, collect([$one, $two])->map(fn ($matrix) => Arr::except($matrix->fresh()->getAttributes(), ['name', 'knowledge_area_id', 'updated_at']))->all());
        $this->assertSame($before, $models->map(fn ($model) => $model->fresh()->getAttributes())->all());
        $this->assertSame($target->name, $record->fresh()->component->name);
        $this->assertSame($target->area->name, $record->fresh()->component->knowledge_area);
        $this->assertSame($target->formation, $record->fresh()->component->formation);
        app(MatrixHistoryCatalog::class)->importAll();
        app(HistoryCatalogIntegrator::class)->syncHistory($history->fresh());
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        $this->assertSame($target->id, $one->historyCatalogEntries()->sole()->id);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'metadata->operation' => 'history-curriculum:merge', 'metadata->updated_matrix_components' => 2]);
    }

    public function test_administrator_can_choose_to_keep_the_original_source_instead(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $one = $this->matrixComponent('Nome que deve prevalecer');
        $two = $this->matrixComponent('Outro nome', 'fundamental', 'Parte Complementar', 'Parte Complementar');
        $source = $one->historyCatalogEntries()->sole();
        $other = $two->historyCatalogEntries()->sole();
        $original = $one->fresh()->getAttributes();
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $other->id, 'keep' => 'source'])->assertSessionHasNoErrors();
        $this->assertSame($original, $one->fresh()->getAttributes());
        $this->assertSame($one->name, $two->fresh()->name);
        $this->assertSame($one->knowledge_area_id, $two->fresh()->knowledge_area_id);
        $this->assertSame($source->id, $two->historyCatalogEntries()->sole()->id);
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $other->id]);
    }

    public function test_merging_matrix_components_rejects_incompatible_stages_without_partial_changes(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $one = $this->matrixComponent('Português fundamental');
        $two = $this->matrixComponent('Português médio', 'medio', 'Linguagens e suas Tecnologias');
        $source = $one->historyCatalogEntries()->sole();
        $target = $two->historyCatalogEntries()->sole();
        $before = [$one->fresh()->getAttributes(), $two->fresh()->getAttributes()];
        foreach (['source', 'target'] as $keep) {
            $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id, 'keep' => $keep])->assertSessionHasErrors('target_component_id');
        }
        $this->assertSame($before, [$one->fresh()->getAttributes(), $two->fresh()->getAttributes()]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $one->id, 'history_curriculum_component_id' => $source->id]);
        $this->assertDatabaseHas('matrix_catalog_components', ['curriculum_component_id' => $two->id, 'history_curriculum_component_id' => $target->id]);
    }

    public function test_components_in_the_same_matrix_keep_both_year_results_and_the_exact_chosen_spelling(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $one = $this->matrixComponent('Nome antigo');
        $two = $one->course->components()->create(['name' => 'Nome mantido', 'knowledge_area_id' => $one->knowledge_area_id, 'workload_hours' => 90]);
        DB::table('curriculum_components')->where('id', $two->id)->update(['name' => 'LIN. INGLESA']);
        $source = $one->historyCatalogEntries()->sole();
        $target = app(MatrixHistoryCatalog::class)->syncComponent($two);
        $history = $this->history();
        $year = $history->years()->create(['label' => '8º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'system']);
        $records = collect([$one, $two])->map(function ($matrix) use ($history, $year) {
            $entry = $matrix->historyCatalogEntries()->sole();
            $row = $history->components()->create(['name' => $entry->name, 'knowledge_area' => $entry->area->name, 'formation' => $entry->formation]);
            $row->catalogEntries()->attach($entry);

            return $row->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $matrix->id, 'score_label' => $matrix->id.',0', 'workload_hours' => $matrix->workload_hours]);
        });
        $before = $records->map(fn ($record) => $record->fresh()->getAttributes())->all();
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $this->assertSame('LIN. INGLESA', $one->fresh()->name);
        $this->assertSame('LIN. INGLESA', $two->fresh()->name);
        $this->assertSame(2, $one->course->components()->count());
        $this->assertSame(2, $year->records()->count());
        $this->assertSame($before, $records->map(fn ($record) => $record->fresh()->getAttributes())->all());
        $this->assertSame($target->id, $one->historyCatalogEntries()->sole()->id);
    }

    public function test_invalid_merge_destinations_are_rejected_and_system_history_references_can_be_merged(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $source = $this->entry('Inglês antigo');
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => 999999])->assertSessionHasErrors('target_component_id');
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $source->id])->assertSessionHasErrors('target_component_id');
        $this->post(route('history-curriculum.merge', $source), [])->assertSessionHasErrors('target_component_id');
        $history = $this->history();
        $year = $history->years()->create(['label' => '8º Ano', 'source' => 'system']);
        $component = $history->components()->create(['name' => $source->name, 'formation' => $source->formation, 'knowledge_area' => $source->area->name]);
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '9,0']);
        $source->historyComponents()->attach($component);
        $target = app(MatrixHistoryCatalog::class)->syncComponent($this->matrixComponent('Lin. Inglesa'));
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $this->assertSame('9,0', $record->fresh()->score_label);
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        $this->assertSame($target->id, $record->fresh()->component->catalogEntries()->sole()->id);
    }

    public function test_orphaned_system_field_cultivation_reference_can_merge_its_two_histories_without_changing_any_matrix(): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $area = KnowledgeArea::where('name', 'Parte Diversificada')->firstOrFail();
        $source = HistoryCurriculumComponent::create(['name' => 'Cultivo de Hortaliças', 'knowledge_area_id' => $area->id,
            'education_stage' => 'medio', 'formation' => 'Itinerário Formativo', 'active' => true]);
        $source->forceFill(['matrix_origin' => true])->save();
        $matrix = $this->matrixComponent('Cultivo de Hortaliças', 'medio', 'Aprofundamento de Estudos', 'Itinerário Formativo');
        $target = $matrix->historyCatalogEntries()->sole();
        $records = collect();
        foreach (range(1, 2) as $number) {
            $history = $this->history();
            $history->update(['is_unified' => true, 'education_stage' => 'medio']);
            $year = $history->years()->create(['label' => '1º Ano', 'stage' => 'Ensino Médio', 'source' => 'system', 'year' => '2025']);
            $component = $history->components()->create(['name' => $source->name, 'knowledge_area' => $area->name, 'formation' => $source->formation]);
            $component->catalogEntries()->attach($source);
            $records->push($component->records()->create(['student_academic_history_year_id' => $year->id, 'curriculum_component_id' => $matrix->id,
                'score_label' => '8,0', 'score_numeric' => 8, 'workload_hours' => 80, 'completed_workload_hours' => 76, 'absences' => 4, 'frequency_percentage' => 95, 'result' => 'Aprovado']));
        }
        $this->assertTrue($source->isMatrixDerived());
        $this->assertFalse($source->matrixComponents()->exists());
        $this->assertSame(2, $source->historyComponents()->count());
        Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Cultivo de Hortaliças')
            ->assertSeeHtml(route('history-curriculum.merge-form', $source))->assertDontSeeHtml('id="catalog-edit-'.$source->id.'"');
        $this->get(route('history-curriculum.merge-form', $source))->assertOk()
            ->assertViewHas('candidates', fn ($candidates) => $candidates->contains('id', $target->id));
        $models = $records->flatMap(fn ($record) => [$record, $record->year])->push($matrix, $matrix->course);
        $before = $models->map(fn ($model) => $model->fresh()->getAttributes())->all();
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        foreach ($records as $record) {
            $component = $record->fresh()->component;
            $this->assertSame($target->name, $component->name);
            $this->assertSame($target->formation, $component->formation);
            $this->assertSame($target->area->name, $component->knowledge_area);
            $this->assertSame($target->id, $component->catalogEntries()->sole()->id);
        }
        app(MatrixHistoryCatalog::class)->importAll();
        app(HistoryCatalogIntegrator::class)->importAll();
        $this->assertSame($before, $models->map(fn ($model) => $model->fresh()->getAttributes())->all());
        $this->assertSame(2, $target->historyComponents()->count());
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $source->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'metadata->operation' => 'history-curriculum:merge',
            'metadata->transferred_links' => 2, 'metadata->updated_matrix_components' => 0]);
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

    public function test_merge_form_lists_catalog_and_matrix_destinations_and_other_roles_cannot_merge(): void
    {
        $source = $this->entry('Inglês antigo');
        $manual = $this->entry('Apenas cadastro');
        $target = app(MatrixHistoryCatalog::class)->syncComponent($this->matrixComponent('Lin. Inglesa', 'medio', 'Linguagens e suas Tecnologias'));
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $this->get(route('history-curriculum.merge-form', $source))->assertOk()->assertSee('Lin. Inglesa')->assertSee($manual->name)->assertSee('id="merge-keep-source"', false);
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('history-curriculum.merge-form', $source))->assertForbidden();
            $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id])->assertForbidden();
        }
    }

    public function test_duplicate_manual_field_science_components_can_keep_the_nature_area(): void
    {
        $this->assertManualDuplicateMerge('source');
    }

    public function test_duplicate_manual_field_science_components_can_keep_the_humanities_area(): void
    {
        $this->assertManualDuplicateMerge('target');
    }

    private function assertManualDuplicateMerge(string $keep): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $entries = collect(['Ciências da Natureza', 'Ciências Humanas'])->map(fn ($area) => HistoryCurriculumComponent::create([
            'name' => 'Ciências e Saberes do Campo', 'education_stage' => 'fundamental', 'formation' => 'Parte Diversificada',
            'knowledge_area_id' => KnowledgeArea::where('name', $area)->value('id'), 'active' => true,
        ]));
        [$source, $target] = $entries->all();
        foreach ([[$source, $target], [$target, $source]] as [$from, $other]) {
            $this->get(route('history-curriculum.merge-form', $from))->assertOk()
                ->assertViewHas('candidates', fn ($candidates) => $candidates->contains('id', $other->id))
                ->assertSee('id="merge-keep-source"', false);
        }
        $records = collect();
        foreach (range(1, 6) as $number) {
            $entry = $number <= 2 ? $source : $target;
            $history = $this->history();
            $row = $history->components()->create(['name' => $entry->name, 'knowledge_area' => $entry->area->name, 'formation' => $entry->formation]);
            $row->catalogEntries()->attach($entry);
            $year = $history->years()->create(['label' => '4º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'manual', 'year' => '2025']);
            $records->push($row->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,0', 'score_numeric' => 8, 'workload_hours' => 80, 'completed_workload_hours' => 76, 'frequency_percentage' => 95, 'absences' => 4, 'result' => 'Aprovado']));
        }
        $before = $records->map(fn ($record) => [$record->fresh()->getAttributes(), $record->year->fresh()->getAttributes()])->all();
        $matrices = CurriculumComponent::orderBy('id')->get()->toArray();
        $kept = $keep === 'source' ? $source : $target;
        $discarded = $keep === 'source' ? $target : $source;
        $this->post(route('history-curriculum.merge', $source), ['target_component_id' => $target->id, 'keep' => $keep])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $discarded->id]);
        $this->assertSame(6, $kept->historyComponents()->count());
        foreach ($records as $record) {
            $row = $record->fresh()->component;
            $this->assertSame($kept->name, $row->name);
            $this->assertSame($kept->formation, $row->formation);
            $this->assertSame($kept->area->name, $row->knowledge_area);
            $this->assertSame($kept->id, $row->catalogEntries()->sole()->id);
        }
        app(HistoryCatalogIntegrator::class)->importAll();
        $this->assertSame($before, $records->map(fn ($record) => [$record->fresh()->getAttributes(), $record->year->fresh()->getAttributes()])->all());
        $this->assertSame($matrices, CurriculumComponent::orderBy('id')->get()->toArray());
        $this->assertDatabaseMissing('history_curriculum_components', ['id' => $discarded->id]);
        $legacy = $this->history();
        $row = $legacy->components()->create(['name' => $discarded->name, 'formation' => $discarded->formation, 'knowledge_area' => $discarded->area->name]);
        $year = $legacy->years()->create(['label' => '5º Ano', 'stage' => 'Ensino Fundamental', 'source' => 'manual']);
        $row->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '9,0']);
        app(HistoryCatalogIntegrator::class)->syncHistory($legacy);
        $this->assertSame($kept->id, $row->catalogEntries()->sole()->id);
        $this->assertSame($kept->area->name, $row->fresh()->knowledge_area);
    }

    public function test_matrix_backfill_reads_database_defaults_and_keeps_imported_spelling_without_repeated_placeholder_areas(): void
    {
        $initial = $this->matrixComponent('Inicial');
        $one = $initial->course->components()->create(['name' => 'Primeiro sem área']);
        $two = $initial->course->components()->create(['name' => 'Segundo sem área']);
        DB::table('curriculum_components')->where('id', $one->id)->update(['name' => 'LIN. INGLESA']);
        $snapshot = [$one->fresh()->getAttributes(), $two->fresh()->getAttributes()];
        (require database_path('migrations/2026_11_05_000005_protect_matrix_catalog_components.php'))->up();
        (require database_path('migrations/2026_11_05_000005_protect_matrix_catalog_components.php'))->up();
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
