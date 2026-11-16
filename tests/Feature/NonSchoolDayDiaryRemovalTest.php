<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Support\NonSchoolDayDiaryRemoval;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NonSchoolDayDiaryRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_apply_remove_only_selected_non_school_day_with_recoverable_backup_and_audit(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        $grades = DiaryAssessment::with('results')->get()->toArray();
        $other = [$s['otherRecord']->fresh('entries')->toArray(), $s['otherContent']->fresh()->toArray()];
        $plan = $service->remove($s['school']->id, 2026, [$s['target']]);
        $this->assertSame('dry-run', $plan['mode']);
        $this->assertSame(1, $plan['attendance_records']);
        $this->assertSame(1, $plan['attendance_entries']);
        $this->assertSame(1, $plan['contents']);
        $this->assertSame(2, $plan['lessons']);
        $this->assertNotNull($s['record']->fresh());
        $applied = $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
        $this->assertNull($s['record']->fresh());
        $this->assertNull($s['content']->fresh());
        $this->assertFalse(DiaryAttendanceEntry::where('diary_attendance_record_id', $s['record']->id)->exists());
        $this->assertSame($grades, DiaryAssessment::with('results')->get()->toArray());
        $this->assertSame($other, [$s['otherRecord']->fresh('entries')->toArray(), $s['otherContent']->fresh()->toArray()]);
        $backup = json_decode(Storage::disk('local')->get($applied['backup_path']), true);
        $this->assertSame($s['record']->id, $backup['attendance'][0]['id']);
        $this->assertSame($s['enrollment']->id, $backup['entries'][0]['student_enrollment_id']);
        $this->assertSame('Conteúdo original', $backup['contents'][0]['content']);
        $this->assertSame(0600, fileperms(Storage::disk('local')->path($applied['backup_path'])) & 0777);
        $this->assertSame(0, $applied['remaining_target_records']);
        $this->assertTrue(AuditLog::where('metadata->operation', NonSchoolDayDiaryRemoval::OPERATION)->exists());
        $this->assertTrue(AuditLog::where('auditable_type', DiaryAttendanceEntry::class)->where('action', 'deleted')->exists());
    }

    public function test_a_school_day_is_rejected_without_any_deletion_even_in_a_mixed_selection(): void
    {
        $s = $this->scenario();
        $other = $s['target'];
        $other['attendance_id'] = $s['otherRecord']->id;
        $other['content_id'] = $s['otherContent']->id;
        $other['date'] = '2026-04-02';
        try {
            app(NonSchoolDayDiaryRemoval::class)->remove($s['school']->id, 2026, [$s['target'], $other], true, str_repeat('0', 64));
            $this->fail('Um dia letivo não pode ser removido.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('marcada como letiva', $e->getMessage());
        }
        $this->assertDatabaseCount('diary_attendance_records', 2);
        $this->assertDatabaseCount('diary_contents', 2);
        $this->assertDatabaseCount('diary_attendance_entries', 2);
    }

    public function test_an_edited_call_invalidates_the_preview_and_preserves_every_record(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        $plan = $service->remove($s['school']->id, 2026, [$s['target']]);
        $s['record']->update(['notes' => 'Alteração posterior']);
        try {
            $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
            $this->fail('Um lançamento alterado exige nova conferência.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mudaram', $e->getMessage());
        }
        $this->assertNotNull($s['record']->fresh());
        $this->assertNotNull($s['content']->fresh());
    }

    public function test_calendar_change_to_a_school_day_after_preview_blocks_removal(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        $plan = $service->remove($s['school']->id, 2026, [$s['target']]);
        $s['year']->days()->whereDate('date', '2026-04-03')->update(['counts_as_school_day' => true]);
        $this->expectExceptionMessage('marcada como letiva');
        $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
    }

    public function test_school_year_and_record_identity_must_match_the_authorized_selection(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        foreach ([[$s['school']->id, 2025, $s['target'], 'fora da escola'],
            [School::create(['name' => 'Outra escola'])->id, 2026, $s['target'], 'fora da escola'],
            [$s['school']->id, 2026, array_replace($s['target'], ['date' => '2026-04-06']), 'não corresponde']] as [$school, $year, $target, $message]) {
            try {
                $service->remove($school, $year, [$target]);
                $this->fail('Um seletor divergente não pode ser aceito.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $this->assertDatabaseCount('diary_attendance_records', 2);
    }

    public function test_a_missing_backup_prevents_deletion(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        $plan = $service->remove($s['school']->id, 2026, [$s['target']]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try {
            $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
            $this->fail('Não é permitido remover sem cópia recuperável.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cópia de segurança', $e->getMessage());
        }
        $this->assertDatabaseCount('diary_attendance_records', 2);
        $this->assertDatabaseCount('diary_contents', 2);
        $this->assertDatabaseCount('diary_attendance_entries', 2);
    }

    public function test_retry_of_an_audited_completed_removal_is_a_noop(): void
    {
        $s = $this->scenario();
        $service = app(NonSchoolDayDiaryRemoval::class);
        $plan = $service->remove($s['school']->id, 2026, [$s['target']]);
        $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
        $again = $service->remove($s['school']->id, 2026, [$s['target']], true, $plan['snapshot_sha256']);
        $this->assertTrue($again['already_removed']);
        $this->assertSame(0, $again['attendance_records']);
        $this->assertNotNull($s['otherRecord']->fresh());
    }

    public function test_a_partially_missing_pair_does_not_remove_the_remaining_record(): void
    {
        $s = $this->scenario();
        $s['content']->delete();
        try {
            app(NonSchoolDayDiaryRemoval::class)->remove($s['school']->id, 2026, [$s['target']], true, str_repeat('0', 64));
            $this->fail('Uma seleção incompleta exige nova conferência.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não existe mais', $e->getMessage());
        }
        $this->assertNotNull($s['record']->fresh());
        $this->assertDatabaseCount('diary_attendance_entries', 2);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-02-02', 'ends_at' => '2026-12-22', 'minimum_school_days' => 200]);
        $period = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2026-02-02', 'ends_at' => '2026-04-10']);
        $year->days()->create(['date' => '2026-04-02', 'type' => 'letivo', 'counts_as_school_day' => true]);
        $year->days()->create(['date' => '2026-04-03', 'type' => 'feriado', 'counts_as_school_day' => false]);
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => 'medio', 'class_hour_minutes' => 50, 'workload_hours' => 800]);
        $component = $course->components()->create(['name' => 'Componente de teste', 'workload_hours' => 80]);
        $class = $year->classes()->create(['name' => '1º Ano']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante de teste']);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        $base = ['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id];
        $record = DiaryAttendanceRecord::create($base + ['class_date' => '2026-04-03', 'lesson_count' => 2]);
        $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 2, 'lesson_presence' => [true, true]]);
        $content = DiaryContent::create($base + ['class_date' => '2026-04-03', 'content' => 'Conteúdo original']);
        $otherRecord = DiaryAttendanceRecord::create($base + ['class_date' => '2026-04-02', 'lesson_count' => 1]);
        $otherRecord->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'falta', 'attended_lessons' => 0]);
        $otherContent = DiaryContent::create($base + ['class_date' => '2026-04-02', 'content' => 'Conteúdo preservado']);
        $assessment = DiaryAssessment::create($base + ['title' => 'Avaliação', 'assessment_date' => '2026-04-03']);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 8.5]);
        $target = ['attendance_id' => $record->id, 'content_id' => $content->id, 'class_id' => $class->id,
            'component_id' => $component->id, 'period_id' => $period->id, 'date' => '2026-04-03'];

        return compact('school', 'year', 'class', 'period', 'course', 'component', 'enrollment', 'record', 'content', 'otherRecord', 'otherContent', 'target');
    }
}
