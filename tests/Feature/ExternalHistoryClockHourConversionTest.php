<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryRecord;
use App\Support\ExternalHistoryClockHourConversion;
use App\Support\HistoryFormationWorkloads;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExternalHistoryClockHourConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_each_component_and_preserves_exact_formation_totals_scores_and_other_years(): void
    {
        [$history, $year, $other] = $this->scenario();
        $service = app(ExternalHistoryClockHourConversion::class);
        $original = $year->records()->orderBy('id')->get()->toArray();
        $otherBefore = $other->fresh('records')->toArray();
        $plan = $service->convert($history->person_id, $history->id, $year->id);
        $this->assertSame('dry-run', $plan['mode']);
        $this->assertSame(18, count($plan['record_changes']));
        $this->assertSame('1200.00', $year->fresh()->workload_hours);
        $this->assertNull($year->fresh()->notes);
        $applied = $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
        $after = $year->fresh('records.component');
        $this->assertSame('1000.00', $after->workload_hours);
        $this->assertSame(ExternalHistoryClockHourConversion::NOTE, $after->notes);
        $this->assertSame(['Formação Geral Básica' => 800.0, 'Itinerário Formativo' => 200.0],
            HistoryFormationWorkloads::yearHours($history->fresh(), $after));
        $this->assertSame(1000.0, round($after->records->sum('workload_hours'), 2));
        foreach ($after->records as $record) {
            $before = collect($original)->firstWhere('id', $record->id);
            foreach (['score_label', 'score_numeric', 'frequency_label', 'frequency_percentage', 'absences', 'result', 'curriculum_component_id'] as $field) {
                $this->assertSame($before[$field], $record->toArray()[$field]);
            }
            $this->assertLessThanOrEqual(.01, abs((float) $record->workload_hours - (float) $before['workload_hours'] * 50 / 60));
        }
        $this->assertSame($otherBefore, $other->fresh('records')->toArray());
        Storage::disk('local')->assertExists($applied['backup_path']);
        $backup = json_decode(Storage::disk('local')->get($applied['backup_path']), true);
        $this->assertCount(18, $backup['records']);
        $this->assertEquals(1200, $backup['year']['workload_hours']);
        $this->assertSame(0600, fileperms(Storage::disk('local')->path($applied['backup_path'])) & 0777);
    }

    public function test_appends_the_note_preserves_existing_notes_and_cannot_convert_a_second_time(): void
    {
        [$history, $year] = $this->scenario();
        $year->update(['notes' => 'Observação de origem.']);
        $service = app(ExternalHistoryClockHourConversion::class);
        $plan = $service->convert($history->person_id, $history->id, $year->id);
        $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
        $this->assertSame("Observação de origem.\n".ExternalHistoryClockHourConversion::NOTE, $year->fresh()->notes);
        $before = $year->fresh('records')->toArray();
        $retry = $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
        $this->assertTrue($retry['already_converted']);
        $this->assertSame($before, $year->fresh('records')->toArray());
    }

    public function test_changed_grade_after_preview_prevents_any_workload_conversion(): void
    {
        [$history, $year] = $this->scenario();
        $service = app(ExternalHistoryClockHourConversion::class);
        $plan = $service->convert($history->person_id, $history->id, $year->id);
        $year->records->first()->update(['score_numeric' => 9]);
        try {
            $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
            $this->fail('Uma nota alterada exige nova conferência.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mudou', $e->getMessage());
        }
        $this->assertSame('1200.00', $year->fresh()->workload_hours);
        $this->assertEquals(1200, $year->records()->sum('workload_hours'));
    }

    public function test_incompatible_source_totals_or_system_year_cannot_be_converted(): void
    {
        [$history, $year, $other] = $this->scenario();
        $service = app(ExternalHistoryClockHourConversion::class);
        try {
            $service->convert($history->person_id, $history->id, $other->id);
            $this->fail('Um ano do sistema não é histórico externo.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('primeiro ano externo', $e->getMessage());
        }
        $year->records->first()->update(['workload_hours' => 100]);
        $this->expectExceptionMessage('cargas de origem');
        $service->convert($history->person_id, $history->id, $year->id);
    }

    public function test_failure_to_save_backup_prevents_all_academic_writes(): void
    {
        [$history, $year] = $this->scenario();
        $service = app(ExternalHistoryClockHourConversion::class);
        $plan = $service->convert($history->person_id, $history->id, $year->id);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try {
            $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
            $this->fail('A cópia precisa existir antes da alteração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cópia de segurança', $e->getMessage());
        }
        $this->assertSame('1200.00', $year->fresh()->workload_hours);
        $this->assertNull($year->fresh()->notes);
        $this->assertEquals(1200, $year->records()->sum('workload_hours'));
    }

    public function test_filled_completed_workloads_are_also_converted(): void
    {
        [$history, $year] = $this->scenario();
        foreach ($year->records as $record) {
            $record->update(['completed_workload_hours' => $record->workload_hours]);
        }
        $service = app(ExternalHistoryClockHourConversion::class);
        $plan = $service->convert($history->person_id, $history->id, $year->id);
        $service->convert($history->person_id, $history->id, $year->id, true, $plan['snapshot_sha256']);
        $this->assertSame(100000, $year->records()->get()->sum(fn ($r) => (int) round((float) $r->completed_workload_hours * 100)));
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $person = Person::create(['full_name' => 'Estudante fictício']);
        $history = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id,
            'title' => 'Histórico escolar', 'stage' => 'Ensino Médio', 'education_stage' => 'medio', 'is_unified' => true, 'active' => true]);
        $year = $history->years()->create(['position' => 1, 'source' => 'external', 'label' => '1º Ano', 'year' => '2025',
            'stage' => 'Ensino Médio', 'transcript_mode' => 'detailed', 'final_result' => 'Aprovado', 'workload_hours' => 1200]);
        $other = $history->years()->create(['position' => 2, 'source' => 'system', 'label' => '2º Ano', 'year' => '2026',
            'stage' => 'Ensino Médio', 'transcript_mode' => 'detailed', 'final_result' => 'Cursando', 'workload_hours' => 800]);
        $all = array_merge(array_map(fn ($h) => ['Formação Geral Básica', $h], [120, 80, 80, 40, 40, 40, 80, 160, 80, 80, 80, 80]),
            array_fill(0, 6, ['Itinerário Formativo', 40]));
        foreach ($all as $position => [$formation, $hours]) {
            $component = $history->components()->create(['position' => $position + 1, 'formation' => $formation,
                'knowledge_area' => 'Área de teste', 'name' => 'Componente '.($position + 1)]);
            StudentAcademicHistoryRecord::create(['student_academic_history_component_id' => $component->id,
                'student_academic_history_year_id' => $year->id, 'workload_hours' => $hours,
                'score_label' => '7,5', 'score_numeric' => 7.5, 'frequency_label' => '90%', 'frequency_percentage' => 90,
                'absences' => 3, 'result' => 'Aprovado']);
            if ($position === 0) {
                StudentAcademicHistoryRecord::create(['student_academic_history_component_id' => $component->id,
                    'student_academic_history_year_id' => $other->id, 'workload_hours' => 800, 'score_label' => '8', 'score_numeric' => 8]);
            }
        }

        return [$history, $year, $other];
    }
}
