<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryYear;
use App\Models\User;
use App\Support\StudentHistoryEditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorySaveProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function history(): StudentAcademicHistory
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa de teste', 'cpf' => '12345678901', 'birth_date' => '1990-01-01',
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
            'stage' => 'Ensino Fundamental', 'education_stage' => 'fundamental', 'is_unified' => false, 'active' => true,
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
