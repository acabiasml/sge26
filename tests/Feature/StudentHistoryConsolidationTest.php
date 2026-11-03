<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\StudentAcademicHistoryCompleteness;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentHistoryConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $stage = 'Ensino Fundamental'): StudentAcademicHistory
    {
        $actor = Person::query()->create([
            'full_name' => 'Administrador de teste', 'cpf' => '12345678901', 'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Responsável', 'address' => 'Rua de teste', 'city' => 'Poxoréu',
            'state' => 'MT', 'postal_code' => '78700000', 'profile_completed_at' => now(),
        ]);
        $actor->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $actor->id]));
        $student = Person::query()->create(['full_name' => 'Estudante transferida']);
        $school = School::query()->create(['name' => 'Escola de teste', 'city' => 'Poxoréu', 'state' => 'MT', 'active' => true]);

        return StudentAcademicHistory::query()->create([
            'person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico escolar',
            'stage' => $stage, 'is_unified' => false, 'active' => true,
            'notes' => 'Observação do documento de origem.',
        ]);
    }

    private function addYear(StudentAcademicHistory $history, int $grade, string $stage = 'Ensino Fundamental'): int
    {
        $year = $history->years()->create([
            'position' => $grade, 'source' => 'manual', 'label' => $grade.'º Ano', 'year' => (string) (2017 + $grade),
            'stage' => $stage, 'grade_phase' => $grade.'º Ano', 'grade_number' => $grade,
            'school_name' => 'Escola de origem', 'transcript_mode' => 'detailed', 'final_result' => 'Aprovado',
            'workload_hours' => 800, 'source_document' => 'Documento de origem', 'school_days' => 200,
        ]);
        $component = $history->components()->create(['name' => 'Matemática', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Matemática', 'position' => 1]);
        $component->records()->create(['student_academic_history_year_id' => $year->id, 'score_label' => '8,5', 'workload_hours' => 160, 'completed_workload_hours' => 155, 'absences' => 2]);

        return $year->id;
    }

    private function synchronize(StudentAcademicHistory $source, string $stage = 'fundamental'): StudentAcademicHistory
    {
        return app(UnifiedStudentHistorySynchronizer::class)->synchronize($source->student, $source->school_id, auth()->user()->person_id, $stage);
    }

    public function test_legacy_years_and_all_record_fields_are_preserved_in_one_stage_and_refresh_is_idempotent(): void
    {
        $source = $this->source();
        $first = $this->addYear($source, 1);
        $this->addYear($source, 2);
        $canonical = $this->synchronize($source);
        $this->assertSame(2, $canonical->years()->count());
        $this->assertSame(0, $source->years()->count());
        $this->assertFalse($source->fresh()->active);
        $this->assertStringContainsString($source->notes, $canonical->notes);
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $first, 'student_academic_history_id' => $canonical->id, 'workload_hours' => 800, 'source_document' => 'Documento de origem']);
        $this->assertDatabaseHas('student_academic_history_records', ['student_academic_history_year_id' => $first, 'score_label' => '8,5', 'workload_hours' => 160, 'completed_workload_hours' => 155, 'absences' => 2]);
        $this->assertTrue(app(StudentAcademicHistoryCompleteness::class)->evaluate($canonical)['complete']);
        $this->post(route('student-histories.unified', [$source->person_id, 'fundamental']))->assertSessionHasNoErrors()->assertRedirect(route('people.histories.show', [$source->person_id, $canonical]));
        $again = $this->synchronize($source);
        $this->assertSame($canonical->id, $again->id);
        $this->assertSame(2, $again->years()->count());
        $this->assertSame(2, $again->components()->first()->records()->count());
    }

    public function test_a_mixed_legacy_document_is_split_by_stage_without_mixing_years(): void
    {
        $source = $this->source();
        $elementary = $this->addYear($source, 1);
        $secondary = $this->addYear($source, 1, 'Ensino Médio');
        app(UnifiedStudentHistorySynchronizer::class)->synchronizeExisting($source->student, auth()->user()->person_id);
        $this->assertSame(2, $source->student->academicHistories()->where('is_unified', true)->count());
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $elementary, 'student_academic_history_id' => $source->student->academicHistories()->where('education_stage', 'fundamental')->sole()->id]);
        $this->assertDatabaseHas('student_academic_history_years', ['id' => $secondary, 'student_academic_history_id' => $source->student->academicHistories()->where('education_stage', 'medio')->sole()->id]);
    }

    public function test_old_pdf_and_editor_urls_point_to_the_canonical_document(): void
    {
        $source = $this->source();
        $this->addYear($source, 1);
        $this->get(route('people.histories.pdf', [$source->person_id, $source]))->assertRedirect();
        $canonical = $source->student->academicHistories()->where('is_unified', true)->sole();
        $this->get(route('people.histories.pdf', [$source->person_id, $source]))->assertRedirect(route('people.histories.pdf', [$source->person_id, $canonical]));
        $this->get(route('people.histories.edit', [$source->person_id, $source]))->assertRedirect(route('people.histories.edit', [$source->person_id, $canonical]));
        $this->assertDatabaseCount('issued_documents', 0);
        $this->put(route('people.histories.update', [$source->person_id, $source]), [])->assertSessionHasErrors('history');
    }

    public function test_conflicting_same_year_sources_block_emission_and_keep_both_records(): void
    {
        $source = $this->source();
        $this->addYear($source, 1);
        $this->addYear($source, 1);
        $canonical = $this->synchronize($source);
        $this->assertFalse(app(StudentAcademicHistoryCompleteness::class)->evaluate($canonical)['complete']);
        $this->get(route('people.histories.pdf', [$source->person_id, $canonical]))->assertRedirect(route('people.histories.show', [$source->person_id, $canonical]));
        $this->assertSame(2, $canonical->years()->count());
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_profile_lists_only_one_document_and_old_creation_cannot_create_a_second_one(): void
    {
        $source = $this->source();
        $this->addYear($source, 1);
        $this->get(route('people.show', $source->person_id))->assertOk();
        $canonical = $source->student->academicHistories()->where('is_unified', true)->sole();
        $this->get(route('people.show', $source->person_id))->assertSee(route('people.histories.pdf', [$source->person_id, $canonical]), false)->assertDontSee(route('people.histories.pdf', [$source->person_id, $source]), false);
        $this->post(route('people.histories.store', $source->person_id), [])->assertSessionHasErrors();
        $this->assertSame(1, $source->student->academicHistories()->where('is_unified', true)->count());
    }

    public function test_new_external_years_append_to_existing_stage_without_creating_another_document(): void
    {
        $source = $this->source();
        $first = $this->addYear($source, 1);
        $canonical = $this->synchronize($source);
        $this->post(route('people.histories.store', $source->person_id), [
            'school_id' => $source->school_id,
            'years' => [[
                'year' => '2019', 'stage' => 'Ensino Fundamental', 'grade_phase' => '2º Ano',
                'modality' => 'Regular', 'school_name' => 'Escola anterior', 'city' => 'Poxoréu',
                'state' => 'MT', 'country' => 'Brasil', 'transcript_mode' => 'detailed',
                'final_result' => 'Aprovado', 'workload_hours' => 800,
            ]],
            'components' => [[
                'name' => 'Matemática', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Matemática',
                'records' => [['score_label' => '9,0', 'workload_hours' => 160]],
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('people.histories.show', [$source->person_id, $canonical]));
        $this->assertSame(1, $source->student->academicHistories()->where('is_unified', true)->count());
        $this->assertSame(2, $canonical->years()->count());
        $this->assertDatabaseHas('student_academic_history_records', ['student_academic_history_year_id' => $first, 'score_label' => '8,5']);
        $this->assertDatabaseHas('student_academic_history_records', ['score_label' => '9,0', 'workload_hours' => 160]);
    }

    public function test_unidentified_series_cannot_be_emitted_as_a_complete_history(): void
    {
        $source = $this->source();
        $yearId = $this->addYear($source, 1);
        $source->years()->whereKey($yearId)->update(['grade_phase' => 'Fase inicial', 'label' => 'Fase inicial', 'grade_number' => null]);
        $canonical = $this->synchronize($source);
        $this->assertFalse(app(StudentAcademicHistoryCompleteness::class)->evaluate($canonical)['complete']);
        $this->get(route('people.histories.pdf', [$source->person_id, $canonical]))->assertRedirect(route('people.histories.show', [$source->person_id, $canonical]));
        $this->assertDatabaseCount('issued_documents', 0);
    }
}
