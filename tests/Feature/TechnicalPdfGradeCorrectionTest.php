<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\TechnicalPdfGradeCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TechnicalPdfGradeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_only_approved_technical_score_and_cached_score_preserving_all_attendance_and_workloads(): void
    {
        [$request, $result, $other, $historyRecord] = $this->scenario();
        $others = $other->fresh()->getAttributes();
        $history = $historyRecord->fresh()->getAttributes();
        $service = app(TechnicalPdfGradeCorrection::class);
        $plan = $service->correct($request);
        $this->assertSame(1, $plan['grades_to_update']);
        $applied = $service->correct($request, true, $plan['snapshot_sha256']);
        $this->assertEquals(6.5, $result->fresh()->score);
        $this->assertSame($others, $other->fresh()->getAttributes());
        $this->assertSame('6,5', $historyRecord->fresh()->score_label);
        $after = $historyRecord->fresh()->getAttributes();
        unset($history['score_label'], $history['updated_at'], $after['score_label'], $after['updated_at']);
        $this->assertSame($history, $after);
        $this->assertSame($plan['protected_sha256'], $service->correct($request)['protected_sha256']);
        $this->assertSame(0, $service->correct($request)['grades_to_update']);
        $this->assertSame(0, $service->correct($request)['history_scores_to_update']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_rejects_stale_snapshot_unapproved_replacement_and_non_technical_course(): void
    {
        [$request, $result, $other] = $this->scenario();
        $service = app(TechnicalPdfGradeCorrection::class);
        $plan = $service->correct($request);
        $other->update(['score' => 8]);
        try {
            $service->correct($request, true, $plan['snapshot_sha256']);
            $this->fail('A edição posterior deve invalidar a simulação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertEquals(6, $result->fresh()->score);
        $unapproved = $request;
        $unapproved['replacement_approved'] = false;
        try {
            $service->correct($unapproved);
            $this->fail('Sem aprovação, não pode corrigir a nota.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('aprovada', $error->getMessage());
        }
        $result->assessment->component->course->update(['stage' => 'medio']);
        $this->expectExceptionMessage('nota técnica aprovada');
        $service->correct($request);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'class_hour_minutes' => 60]);
        $period = $year->periods()->create(['name' => 'Módulo I', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-09-15']);
        $course = $year->courses()->create(['name' => 'Técnico em Móveis', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'workload_hours' => 120]);
        $component = $course->components()->create(['name' => 'Equipamentos', 'workload_hours' => 120, 'active' => true]);
        $class = $year->classes()->create(['name' => 'Turma técnica']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante técnico']);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'title' => 'Avaliação', 'is_recovery' => false, 'weight' => 10, 'maximum_score' => 10]);
        $result = $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 6]);
        $otherStudent = Person::create(['full_name' => 'Outro estudante']);
        $otherEnrollment = $class->enrollments()->create(['person_id' => $otherStudent->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $other = $assessment->results()->create(['student_enrollment_id' => $otherEnrollment->id, 'score' => 8.25]);
        $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-03', 'lesson_count' => 2]);
        $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'falta', 'attended_lessons' => 0]);
        DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-03', 'content' => 'Conteúdo preservado']);
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico técnico',
            'education_stage' => 'tecnico', 'technical_course_id' => $course->id]);
        $historyYear = $history->years()->create(['student_enrollment_id' => $enrollment->id, 'source' => 'system', 'year' => '2025', 'label' => 'Módulo I']);
        $historyComponent = $history->components()->create(['name' => 'Equipamentos', 'position' => 1]);
        $historyRecord = $historyComponent->records()->create(['student_academic_history_year_id' => $historyYear->id,
            'curriculum_component_id' => $component->id, 'score_label' => '6,0', 'workload_hours' => 120, 'completed_workload_hours' => 118, 'absences' => 2]);
        $request = ['replacement_approved' => true, 'source_grade_kind' => 'reported_average', 'source_pdf_sha256' => str_repeat('a', 64),
            'course_id' => $course->id, 'component_id' => $component->id, 'class_id' => $class->id, 'period_id' => $period->id,
            'enrollment_id' => $enrollment->id, 'person_id' => $student->id, 'result_id' => $result->id,
            'reference_year' => 2025, 'expected_score' => 6, 'score' => 6.5];

        return [$request, $result, $other, $historyRecord];
    }
}
