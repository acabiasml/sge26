<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\LegacyClassDiaryReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LegacyClassDiaryReconcilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciles_closed_year_without_changing_grades_or_existing_attendance_and_is_repeatable(): void
    {
        [$manifest, $course, $class, $enrollment, $historyRecord] = $this->scenario();
        $service = app(LegacyClassDiaryReconciler::class);
        $grades = DiaryAssessmentResult::get()->toArray();
        $original = DiaryAttendanceRecord::with('entries')->get()->toArray();
        $plan = $service->reconcile($manifest);
        $this->assertSame(1, $plan['stats']['calls_to_create']);
        $this->assertSame(1, $plan['stats']['entries_to_create']);
        $this->assertEquals(200, $course->fresh()->workload_hours);
        $this->assertDatabaseCount('diary_attendance_records', 1);
        $applied = $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertEquals(40, $course->fresh()->workload_hours);
        $this->assertEquals(60, $course->fresh()->class_hour_minutes);
        $this->assertEquals(40, $course->components()->sole()->workload_hours);
        $this->assertEquals(1, $course->components()->sole()->weekly_lessons);
        $this->assertSame($grades, DiaryAssessmentResult::get()->toArray());
        $this->assertSame($original, DiaryAttendanceRecord::with('entries')->whereKey($original[0]['id'])->get()->toArray());
        $this->assertDatabaseCount('diary_attendance_records', 2);
        $this->assertDatabaseCount('diary_attendance_entries', 2);
        $this->assertSame('Conteúdo manual preservado', DiaryContent::whereDate('class_date', '2025-02-03')->sole()->content);
        $this->assertEquals(1, $historyRecord->fresh()->completed_workload_hours);
        $this->assertEquals(40, $historyRecord->fresh()->workload_hours);
        $this->assertEquals(2, $historyRecord->fresh()->absences);
        $this->assertSame('8,0', $historyRecord->fresh()->score_label);
        $this->assertEquals(40, $historyRecord->year->fresh()->workload_hours);
        $this->assertSame(1, $applied['history_years_refreshed']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        $next = $service->reconcile($manifest);
        $this->assertSame(0, $next['stats']['calls_to_create']);
        $this->assertSame(0, $next['stats']['entries_to_create']);
        $this->assertSame(0, $next['stats']['matrix_changes']);
        $service->reconcile($manifest, true, $next['snapshot_sha256']);
        $this->assertDatabaseCount('diary_attendance_records', 2);
        $this->assertDatabaseCount('diary_attendance_entries', 2);
        $this->assertSame($grades, DiaryAssessmentResult::get()->toArray());
    }

    public function test_changes_after_dry_run_are_rejected_before_matrix_or_attendance_is_written(): void
    {
        [$manifest, $course] = $this->scenario();
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        DiaryContent::sole()->update(['content' => 'Uma edição posterior']);
        try {
            $service->reconcile($manifest, true, $plan['snapshot_sha256']);
            $this->fail('Uma edição concorrente deveria interromper a aplicação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudou', $error->getMessage());
        }
        $this->assertEquals(200, $course->fresh()->workload_hours);
        $this->assertDatabaseCount('diary_attendance_records', 1);
    }

    public function test_conflicting_attendance_and_wrong_student_identity_are_not_overwritten(): void
    {
        [$manifest, $course] = $this->scenario();
        $manifest['documents'][0]['sessions'][0]['attendance']['Estudante de Teste'] = 'absent';
        try {
            app(LegacyClassDiaryReconciler::class)->reconcile($manifest);
            $this->fail('A marcação conflitante deveria bloquear a operação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('presença existente', $error->getMessage());
        }
        $manifest['documents'][0]['sessions'][0]['attendance']['Estudante de Teste'] = 'present';
        $manifest['documents'][0]['students'][0]['legacy_student_id'] = 999;
        try {
            app(LegacyClassDiaryReconciler::class)->reconcile($manifest);
            $this->fail('Uma identidade divergente deveria bloquear a operação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('identidade', $error->getMessage());
        }
        $this->assertEquals(200, $course->fresh()->workload_hours);
        $this->assertDatabaseCount('diary_attendance_records', 1);
    }

    public function test_failure_refreshing_history_rolls_back_matrix_and_new_attendance(): void
    {
        [$manifest, $course, , , $record] = $this->scenario();
        $record->update(['curriculum_component_id' => null]);
        $record->component->update(['name' => 'Componente sem correspondência']);
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        try {
            $service->reconcile($manifest, true, $plan['snapshot_sha256']);
            $this->fail('A falha no histórico deveria reverter toda a operação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('histórico', $error->getMessage());
        }
        $this->assertEquals(200, $course->fresh()->workload_hours);
        $this->assertDatabaseCount('diary_attendance_records', 1);
        $this->assertDatabaseCount('diary_attendance_entries', 1);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de Teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano 2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'closed_at' => '2025-12-31', 'minimum_school_days' => 200]);
        $course = $year->courses()->create(['name' => '9º Ano', 'stage' => 'fundamental', 'class_hour_minutes' => 50, 'workload_hours' => 200]);
        $component = $course->components()->create(['name' => 'Língua Inglesa', 'workload_hours' => 200, 'weekly_lessons' => 6, 'active' => true]);
        $period = $year->periods()->create(['name' => '1º Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-04-30']);
        $class = $year->classes()->create(['name' => '9º Ano']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante de Teste', 'legacy_source' => 'laura', 'legacy_id' => 56]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'title' => 'Avaliação existente', 'is_recovery' => false]);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 0]);
        $attendance = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-02-03', 'lesson_count' => 1]);
        $attendance->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-02-03', 'content' => 'Conteúdo manual preservado']);
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico']);
        $historyYear = $history->years()->create(['student_enrollment_id' => $enrollment->id, 'year' => '2025', 'source' => 'system', 'label' => '9º Ano', 'workload_hours' => 200]);
        $historyComponent = $history->components()->create(['name' => 'Língua Inglesa', 'position' => 1]);
        $historyRecord = $historyComponent->records()->create(['student_academic_history_year_id' => $historyYear->id,
            'curriculum_component_id' => $component->id, 'score_label' => '8,0', 'workload_hours' => 200, 'completed_workload_hours' => 0.83]);
        $manifest = ['format' => 'legacy-diary-pdf-manifest-v1', 'failures' => [],
            'target' => ['class_id' => $class->id, 'course_id' => $course->id, 'academic_year_id' => $year->id,
                'school_id' => $school->id, 'reference_year' => 2025, 'source' => 'laura'],
            'matrix' => ['class_hour_minutes' => 60, 'components' => [['name' => 'Língua Inglesa', 'workload_hours' => 40, 'weekly_lessons' => 1]]],
            'documents' => [['file' => 'source.pdf', 'sha256' => str_repeat('a', 64), 'reference_year' => 2025,
                'source' => 'laura', 'class_name' => '9º Ano', 'component_name' => 'Língua Inglesa', 'issues' => [],
                'students' => [['legacy_student_id' => 56, 'student_name' => 'Estudante de Teste', 'grades' => ['1' => 0]]],
                'sessions' => [
                    ['date' => '2025-02-03', 'period' => 1, 'lesson_count' => 1, 'content' => 'Descrição do PDF', 'attendance' => ['Estudante de Teste' => 'present']],
                    ['date' => '2025-02-04', 'period' => 1, 'lesson_count' => 2, 'content' => 'Conteúdo ausente', 'attendance' => ['Estudante de Teste' => 'absent']],
                ],
            ]]];

        return [$manifest, $course, $class, $enrollment, $historyRecord];
    }
}
