<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\VerifiedMatrixCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifiedMatrixCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_only_documented_excesses_and_refreshes_generated_history_without_altering_source_grades(): void
    {
        [$request, $course, $biology, $historyComponent, $enrollment, $generated, $external] = $this->scenario();
        $beforeGrades = $enrollment->assessmentResults()->orderBy('id')->get()->toArray();
        $beforeManual = $external->fresh()->getAttributes();
        $service = app(VerifiedMatrixCorrection::class);
        $plan = $service->reconcile($request);
        $this->assertSame(2, $plan['components_to_update']);
        $this->assertSame(1, $plan['final_results_to_update']);
        $this->assertTrue($biology->fresh()->active);
        $applied = $service->reconcile($request, true, $plan['snapshot_sha256']);
        $this->assertFalse($biology->fresh()->active);
        $this->assertEquals(40, $historyComponent->fresh()->workload_hours);
        $this->assertEquals(480, $course->fresh()->workload_hours);
        $this->assertSame(480.0, $course->fresh()->calculatedWorkloadHours());
        $this->assertSame('aprovado', $enrollment->fresh()->final_result_status);
        $this->assertSame($beforeGrades, $enrollment->assessmentResults()->orderBy('id')->get()->toArray());
        $this->assertSame($beforeManual, $external->fresh()->getAttributes());
        $this->assertDatabaseCount('diary_attendance_entries', 1);
        $this->assertSame(0, $generated->records()->where('curriculum_component_id', $biology->id)->count());
        $this->assertEquals(480, $generated->fresh()->workload_hours);
        $this->assertSame($plan['protected_sha256'], $service->reconcile($request)['protected_sha256']);
        $again = $service->reconcile($request);
        $this->assertSame(0, $again['components_to_update'] + $again['courses_to_update'] + $again['final_results_to_update']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_rejects_new_grade_after_dry_run_and_never_changes_unreviewed_matrix(): void
    {
        [$request, $course, $biology, $historyComponent, $enrollment] = $this->scenario();
        $service = app(VerifiedMatrixCorrection::class);
        $plan = $service->reconcile($request);
        $enrollment->assessmentResults()->first()->update(['score' => 8]);
        $this->expectExceptionMessage('mudaram após');
        $service->reconcile($request, true, $plan['snapshot_sha256']);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-03', 'ends_at' => '2025-12-19', 'closed_at' => '2026-01-01', 'passing_points' => 24]);
        $course = $year->courses()->create(['name' => '2º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60, 'workload_hours' => 560]);
        $class = $year->classes()->create(['name' => '2º Ano']);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Estudante']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'status' => 'matriculado', 'final_result_status' => 'retido_por_pontos']);
        $enrollment->courses()->attach($course);
        $math = $course->components()->create(['name' => 'Matemática', 'workload_hours' => 440, 'active' => true]);
        $history = $course->components()->create(['name' => 'História', 'workload_hours' => 80, 'weekly_lessons' => 2, 'active' => true]);
        $biology = $course->components()->create(['name' => 'Biologia', 'workload_hours' => 40, 'active' => true]);
        for ($i = 1; $i <= 4; $i++) {
            $period = $year->periods()->create(['name' => 'Bimestre '.$i, 'position' => $i, 'starts_at' => '2025-02-03', 'ends_at' => '2025-12-19']);
            foreach ([$math, $history, $biology] as $component) {
                $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                    'academic_period_id' => $period->id, 'title' => 'Avaliação', 'weight' => 10, 'maximum_score' => 10]);
                $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 7]);
            }
        }
        $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $history->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-03', 'lesson_count' => 2]);
        $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 2]);
        $document = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id, 'education_stage' => 'medio', 'is_unified' => true, 'active' => true]);
        $generated = $document->years()->create(['label' => '2º Ano', 'year' => '2025', 'source' => 'system', 'student_enrollment_id' => $enrollment->id, 'workload_hours' => 560]);
        foreach ([$math, $history, $biology] as $component) {
            $catalog = $document->components()->create(['name' => $component->name]);
            $catalog->records()->create(['student_academic_history_year_id' => $generated->id, 'curriculum_component_id' => $component->id,
                'workload_hours' => $component->workload_hours, 'score_label' => '7,0']);
        }
        $manualYear = $document->years()->create(['source' => 'external', 'label' => 'Ano externo']);
        $external = $catalog->records()->create(['student_academic_history_year_id' => $manualYear->id, 'workload_hours' => 40, 'score_label' => '8,0']);
        $request = ['format' => 'verified-matrix-correction-v1', 'user_confirmed' => true, 'school_id' => $school->id,
            'refresh_final_results_for_class_ids' => [$class->id], 'courses' => [['course_id' => $course->id, 'reference_year' => 2025,
                'source_pdf_sha256' => str_repeat('a', 64), 'class_hour_minutes' => 60, 'expected_active_hours' => 480,
                'components' => [['id' => $biology->id, 'active' => false], ['id' => $history->id, 'weekly_lessons' => 1, 'workload_hours' => 40]]]]];

        return [$request, $course, $biology, $history, $enrollment, $generated, $external];
    }
}
