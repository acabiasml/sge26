<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\StudentFinalResultCalculator;
use App\Support\VerifiedLegacyOutcomeCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifiedLegacyOutcomeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_respects_documented_rounded_approval_without_changing_original_scores_and_survives_recalculation(): void
    {
        [$request, $enrollment, $year, $record] = $this->scenario();
        $scores = $enrollment->assessmentResults()->orderBy('id')->get()->toArray();
        $recordBefore = $record->fresh()->getAttributes();
        $service = app(VerifiedLegacyOutcomeCorrection::class);
        $plan = $service->reconcile($request);
        $this->assertSame(1, $plan['enrollments_to_update']);
        $this->assertSame('retido_por_pontos', $enrollment->fresh()->final_result_status);
        $service->reconcile($request, true, $plan['snapshot_sha256']);
        $enrollment->refresh();
        $this->assertSame('aprovado', $enrollment->final_result_status);
        $this->assertSame('aprovado', app(StudentFinalResultCalculator::class)->calculate($enrollment)['status']);
        $this->assertSame('verified_legacy_result', app(StudentFinalResultCalculator::class)->calculate($enrollment)['details']['basis']);
        $this->assertSame($scores, $enrollment->assessmentResults()->orderBy('id')->get()->toArray());
        $this->assertSame('Aprovado', $year->fresh()->final_result);
        $this->assertSame('Aprovado', $record->fresh()->result);
        $after = $record->fresh()->getAttributes();
        unset($recordBefore['result'], $recordBefore['updated_at'], $after['result'], $after['updated_at']);
        $this->assertSame($recordBefore, $after);
        $again = $service->reconcile($request);
        $this->assertSame(0, $again['enrollments_to_update'] + $again['history_years_to_update']);
        $this->assertSame($plan['protected_sha256'], $again['protected_sha256']);
        $enrollment->update(['status' => 'transferido', 'transferred_at' => '2024-12-20']);
        $this->assertSame('transferido', app(StudentFinalResultCalculator::class)->calculate($enrollment)['status']);
    }

    public function test_rejects_stale_scores_and_source_that_does_not_confirm_approval(): void
    {
        [$request, $enrollment] = $this->scenario();
        $service = app(VerifiedLegacyOutcomeCorrection::class);
        $plan = $service->reconcile($request);
        $enrollment->assessmentResults()->first()->update(['score' => 4]);
        try {
            $service->reconcile($request, true, $plan['snapshot_sha256']);
            $this->fail('A edição posterior precisa invalidar a conferência.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertSame('retido_por_pontos', $enrollment->fresh()->final_result_status);
        $request['enrollments'][0]['source_documents'][0]['reported_result'] = 'reprovado';
        $this->expectExceptionMessage('não comprova aprovação');
        $service->reconcile($request);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024', 'reference_year' => 2024,
            'starts_at' => '2024-02-01', 'ends_at' => '2024-12-20', 'closed_at' => '2025-01-01', 'passing_points' => 24]);
        $course = $year->courses()->create(['name' => '3º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $class = $year->classes()->create(['name' => '3º Ano']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante', 'legacy_source' => 'beaba', 'legacy_id' => 34]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'status' => 'matriculado', 'final_result_status' => 'retido_por_pontos']);
        $enrollment->courses()->attach($course);
        $component = $course->components()->create(['name' => 'Matemática', 'active' => true, 'workload_hours' => 120]);
        foreach ([6, 5, 6, 6] as $i => $score) {
            $period = $year->periods()->create(['name' => 'Bimestre '.($i + 1), 'position' => $i + 1, 'starts_at' => '2024-02-01', 'ends_at' => '2024-12-20']);
            $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'title' => 'Avaliação', 'weight' => 10, 'maximum_score' => 10]);
            $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $score]);
        }
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'education_stage' => 'medio', 'is_unified' => true, 'active' => true]);
        $historyYear = $history->years()->create(['source' => 'system', 'student_enrollment_id' => $enrollment->id,
            'label' => '3º Ano', 'year' => '2024', 'final_result' => 'Retido por Pontos']);
        $historyComponent = $history->components()->create(['name' => 'Matemática']);
        $record = $historyComponent->records()->create(['student_academic_history_year_id' => $historyYear->id,
            'curriculum_component_id' => $component->id, 'score_label' => '6,0', 'workload_hours' => 120, 'result' => 'Retido por Pontos']);
        $request = ['format' => 'verified-legacy-approval-v1', 'confirmed_by_management' => true,
            'person_id' => $student->id, 'school_id' => $school->id, 'source' => 'beaba', 'legacy_student_id' => 34,
            'enrollments' => [['enrollment_id' => $enrollment->id, 'reference_year' => 2024, 'source_documents' => [
                ['component_id' => $component->id, 'sha256' => str_repeat('a', 64), 'reported_average' => 6, 'reported_result' => 'aprovado'],
            ]]]];

        return [$request, $enrollment, $historyYear, $record];
    }
}
