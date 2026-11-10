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
use App\Support\LegacyDiaryBatchReconciler;
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

    public function test_persisted_origin_grade_does_not_create_attendance_before_enrollment(): void
    {
        [$manifest, , , $enrollment] = $this->scenario();
        $enrollment->update(['enrolled_at' => '2025-05-01']);
        DiaryAttendanceRecord::query()->get()->each(fn ($record) => $record->entries()->delete());
        DiaryAttendanceRecord::query()->delete();
        foreach ($manifest['documents'][0]['sessions'] as &$session) {
            $session['attendance'] = [];
        }
        unset($session);
        $plan = app(LegacyClassDiaryReconciler::class)->reconcile($manifest);
        $this->assertSame(0, $plan['stats']['entries_to_create']);
        $this->assertSame(1, $plan['stats']['existing_grades_preserved']);
        $this->assertDatabaseHas('diary_assessment_results', ['student_enrollment_id' => $enrollment->id, 'score' => 0]);
    }

    public function test_authoritative_source_corrects_marks_and_removes_only_explicit_hash_exclusions(): void
    {
        [$manifest, , $class] = $this->scenario();
        $other = Person::create(['full_name' => 'Outro Estudante', 'legacy_source' => 'laura', 'legacy_id' => 57]);
        $enrollment = $class->enrollments()->create(['person_id' => $other->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $record = DiaryAttendanceRecord::sole();
        $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $manifest['source_authoritative'] = true;
        $manifest['documents'][0]['sessions'][1]['lesson_count_basis'] = ['method' => 'source_pattern', 'user_authorized' => true];
        $manifest['documents'][0]['students'][] = ['legacy_student_id' => 57, 'student_name' => $other->full_name, 'grades' => []];
        $manifest['documents'][0]['sessions'][0]['not_enrolled'] = [$other->full_name];
        $manifest['documents'][0]['sessions'][0]['attendance']['Estudante de Teste'] = 'absent';
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(1, $plan['stats']['entries_to_correct']);
        $this->assertSame(1, $plan['stats']['entries_to_remove']);
        $this->assertSame(1, $plan['stats']['estimated_sessions']);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertDatabaseMissing('diary_attendance_entries', ['student_enrollment_id' => $enrollment->id]);
        $this->assertEquals(0, $record->entries()->sole()->attended_lessons);
        $this->assertSame('falta', $record->entries()->sole()->status);
        $this->assertSame('source_pattern', DiaryAttendanceRecord::whereDate('class_date', '2025-02-04')->sole()->legacy_metadata['lesson_count_basis']['method']);
        $next = $service->reconcile($manifest);
        $this->assertSame(0, $next['stats']['entries_to_correct']);
        $this->assertSame(0, $next['stats']['entries_to_remove']);
    }

    public function test_unreported_quantity_is_preserved_when_known_and_deferred_when_missing(): void
    {
        [$manifest, $course] = $this->scenario();
        $course->components()->sole()->update(['weekly_lessons' => null]);
        $manifest['matrix']['components'][0]['weekly_lessons'] = null;
        $manifest['defer_incomplete_sessions'] = true;
        foreach ($manifest['documents'][0]['sessions'] as &$session) {
            unset($session['lesson_count']);
        }
        unset($session);
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(1, $plan['stats']['sessions_missing_quantity']);
        $this->assertSame(0, $plan['stats']['calls_to_create']);
        $this->assertSame('2025-02-04', $plan['deferred'][0]['date']);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertDatabaseCount('diary_attendance_records', 1);
        $this->assertEquals(1, DiaryAttendanceRecord::sole()->lesson_count);
    }

    public function test_rounding_permission_preserves_scores_and_does_not_hide_real_grade_conflicts(): void
    {
        [$manifest] = $this->scenario();
        DiaryAssessmentResult::sole()->update(['score' => 8.2]);
        $manifest['allow_grade_rounding'] = true;
        $manifest['documents'][0]['students'][0]['grades']['1'] = 8.2;
        $manifest['defer_incomplete_sessions'] = true;
        $manifest['documents'][0]['sessions'] = [];
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertEquals(8.2, DiaryAssessmentResult::sole()->score);
        $this->assertDatabaseCount('diary_attendance_records', 1);
        $manifest['documents'][0]['students'][0]['grades']['1'] = 7.2;
        $this->expectExceptionMessage('divergência nas notas');
        $service->reconcile($manifest);
    }

    public function test_outside_enrollment_marks_are_reported_without_changing_enrollment_or_creating_entries(): void
    {
        [$manifest, , , $enrollment] = $this->scenario();
        $enrollment->update(['enrolled_at' => '2025-05-01']);
        $manifest['defer_incomplete_sessions'] = true;
        $service = app(LegacyClassDiaryReconciler::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(2, $plan['stats']['marks_outside_enrollment']);
        $this->assertSame(0, $plan['stats']['entries_to_create']);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertSame('2025-05-01', $enrollment->fresh()->enrolled_at->toDateString());
        $this->assertDatabaseCount('diary_attendance_entries', 1);
    }

    public function test_batch_rolls_back_all_classes_when_a_later_history_fails_and_rejects_changed_manifest(): void
    {
        [$first, $course] = $this->scenario();
        [$second, , , , $historyRecord] = $this->scenario();
        $sameYear = $course->academicYear;
        $extraCourse = $sameYear->courses()->create(['name' => 'Outra matriz', 'class_hour_minutes' => 50, 'workload_hours' => 80]);
        $otherYear = AcademicYear::create(['school_id' => $sameYear->school_id, 'reference_year' => 2024,
            'name' => 'Ano 2024', 'starts_at' => '2024-01-01', 'ends_at' => '2024-12-31', 'class_hour_minutes' => 50]);
        $priorCourse = $otherYear->courses()->create(['name' => 'Matriz anterior', 'class_hour_minutes' => 50, 'workload_hours' => 40]);
        $batch = ['format' => 'legacy-diary-batch-v1', 'classes' => [$first, $second], 'school_year_class_hours' => [
            ['school_id' => $sameYear->school_id, 'reference_year' => 2024, 'class_hour_minutes' => 60],
            ['school_id' => $sameYear->school_id, 'reference_year' => 2025, 'class_hour_minutes' => 60],
        ]];
        $service = app(LegacyDiaryBatchReconciler::class);
        $plan = $service->reconcile($batch);
        $changed = $batch;
        $changed['classes'][0]['matrix']['components'][0]['workload_hours'] = 80;
        try {
            $service->reconcile($changed, true, $plan['snapshot_sha256']);
            $this->fail('Alterar o manifesto deveria invalidar a conferência.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('lote mudou', $error->getMessage());
        }
        $historyRecord->update(['curriculum_component_id' => null]);
        $historyRecord->component->update(['name' => 'Sem correspondência']);
        $plan = $service->reconcile($batch);
        try {
            $service->reconcile($batch, true, $plan['snapshot_sha256']);
            $this->fail('A falha posterior deve reverter o lote inteiro.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('histórico', $error->getMessage());
        }
        $this->assertEquals(200, $course->fresh()->workload_hours);
        $this->assertEquals(50, $extraCourse->fresh()->class_hour_minutes);
        $this->assertEquals(50, $priorCourse->fresh()->class_hour_minutes);
        $this->assertDatabaseCount('diary_attendance_records', 2);
        $this->assertDatabaseCount('diary_attendance_entries', 2);
        $historyRecord->component->update(['name' => 'Língua Inglesa']);
        $plan = $service->reconcile($batch);
        $applied = $service->reconcile($batch, true, $plan['snapshot_sha256']);
        $this->assertSame('apply', $applied['mode']);
        $this->assertEquals(60, $extraCourse->fresh()->class_hour_minutes);
        $this->assertEquals(60, $priorCourse->fresh()->class_hour_minutes);
        $this->assertEquals(60, $otherYear->fresh()->class_hour_minutes);
        $this->assertEquals(60, $sameYear->fresh()->class_hour_minutes);
        $this->assertSame(0, $service->reconcile($batch)['school_year_class_hours'][0]['courses_to_update']);
        $this->assertDatabaseCount('diary_attendance_records', 4);
        $this->assertDatabaseCount('diary_attendance_entries', 4);
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
        $student = Person::create(['full_name' => 'Estudante de Teste', 'legacy_source' => 'laura', 'legacy_id' => 56 + Person::count()]);
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
                'students' => [['legacy_student_id' => (int) $student->legacy_id, 'student_name' => 'Estudante de Teste', 'grades' => ['1' => 0]]],
                'sessions' => [
                    ['date' => '2025-02-03', 'period' => 1, 'lesson_count' => 1, 'content' => 'Descrição do PDF', 'attendance' => ['Estudante de Teste' => 'present']],
                    ['date' => '2025-02-04', 'period' => 1, 'lesson_count' => 2, 'content' => 'Conteúdo ausente', 'attendance' => ['Estudante de Teste' => 'absent']],
                ],
            ]]];

        return [$manifest, $course, $class, $enrollment, $historyRecord];
    }
}
