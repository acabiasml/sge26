<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
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

    public function test_real_pdf_emission_preserves_verified_approval_after_synchronization_and_cannot_be_stored_in_cache(): void
    {
        [$request, $enrollment, $year] = $this->scenario();
        $service = app(VerifiedLegacyOutcomeCorrection::class);
        $plan = $service->reconcile($request);
        $service->reconcile($request, true, $plan['snapshot_sha256']);
        $history = $year->history;
        $student = $enrollment->student;
        $student->update(['cpf' => '12345678901', 'birth_date' => '2006-01-01', 'mother_name' => 'Maria']);
        $school = $history->school;
        $school->update(['legal_name' => 'Escola', 'cnpj' => '12345678000199', 'inep' => '12345678',
            'founded_at' => '2000-01-01', 'phone' => '66999999999', 'email' => 'escola@example.org',
            'letterhead_text' => 'Autorização de funcionamento', 'address' => 'Rua A',
            'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78700000']);
        $actor = Person::create(['full_name' => 'Administrador', 'cpf' => '98765432100', 'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Maria', 'address' => 'Rua A', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $actor->schoolRoles()->create(['role' => PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true]);
        $this->actingAs(User::factory()->create(['person_id' => $actor->id]));
        foreach ([1, 2] as $grade) {
            $history->years()->create(['source' => 'external', 'grade_number' => $grade, 'grade_phase' => $grade.'º Ano',
                'label' => $grade.'º Ano', 'year' => (string) (2021 + $grade), 'final_result' => 'Aprovado',
                'transcript_mode' => 'detailed', 'stage' => 'Ensino Médio']);
        }
        $renderedResults = [];
        view()->composer('reports.student-academic-history', function ($view) use (&$renderedResults): void {
            $renderedResults = $view->getData()['history']->years->pluck('final_result')->all();
        });
        $scores = $enrollment->assessmentResults()->get()->toArray();
        $firstUrl = $history->freshPdfUrl();
        $this->assertNotSame($firstUrl, $history->freshPdfUrl());
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->get($history->freshPdfUrl())->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame(['Aprovado', 'Aprovado', 'Aprovado'], $renderedResults);
            $this->assertSame('Aprovado', $year->fresh()->final_result);
        }
        $this->assertSame($scores, $enrollment->assessmentResults()->get()->toArray());
    }

    public function test_batch_accepts_documented_students_in_different_schools_and_elementary_stage(): void
    {
        [$first, $enrollment] = $this->scenario();
        [$second, $elementary] = $this->scenario('laura');
        $elementary->courses()->first()->update(['stage' => 'fundamental']);
        $targets = [];
        foreach ([[$first, $enrollment], [$second, $elementary]] as [$source, $row]) {
            $target = $source['enrollments'][0];
            foreach (['person_id', 'school_id', 'source', 'legacy_student_id'] as $field) {
                $target[$field] = $source[$field];
            }
            $target['source_documents'][0]['period_grades'] = ['1' => 6, '2' => 5, '3' => 6, '4' => 6];
            $targets[] = $target;
        }
        $request = ['format' => 'verified-legacy-approval-v1', 'confirmed_by_management' => true,
            'validate_current_diary_grades' => true, 'enrollments' => $targets];
        $service = app(VerifiedLegacyOutcomeCorrection::class);
        $plan = $service->reconcile($request);
        $this->assertSame(2, $plan['enrollments_to_update']);
        $service->reconcile($request, true, $plan['snapshot_sha256']);
        $this->assertSame('aprovado', $enrollment->fresh()->final_result_status);
        $this->assertSame('aprovado', $elementary->fresh()->final_result_status);
        $this->assertSame(0, $service->reconcile($request)['enrollments_to_update']);
    }

    public function test_batch_does_not_overrule_a_later_reduction_of_the_grade_recorded_in_the_approved_pdf(): void
    {
        [$request, $enrollment] = $this->scenario();
        $request['validate_current_diary_grades'] = true;
        $request['enrollments'][0]['source_documents'][0]['period_grades'] = ['1' => 6, '2' => 5, '3' => 6, '4' => 6];
        $enrollment->assessmentResults()->first()->update(['score' => 4]);
        $this->expectExceptionMessage('abaixo do diário aprovado');
        app(VerifiedLegacyOutcomeCorrection::class)->reconcile($request);
    }

    private function scenario(string $source = 'beaba'): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024', 'reference_year' => 2024,
            'starts_at' => '2024-02-01', 'ends_at' => '2024-12-20', 'closed_at' => '2025-01-01', 'passing_points' => 24]);
        $course = $year->courses()->create(['name' => '3º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $class = $year->classes()->create(['name' => '3º Ano']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante', 'legacy_source' => $source, 'legacy_id' => 34]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'status' => 'matriculado', 'final_result_status' => 'retido_por_pontos']);
        $enrollment->courses()->attach($course);
        $component = $course->components()->create(['name' => 'Matemática', 'active' => true, 'workload_hours' => 120]);
        foreach ([6, 5, 6, 6] as $i => $score) {
            $period = $year->periods()->create(['name' => 'Bimestre '.($i + 1), 'position' => $i + 1, 'starts_at' => '2024-02-01', 'ends_at' => '2024-12-20']);
            $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'title' => 'Avaliação', 'weight' => 10, 'maximum_score' => 10]);
            $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $score]);
        }
        $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2024-03-01', 'lesson_count' => 1]);
        $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'education_stage' => 'medio', 'is_unified' => true, 'active' => true]);
        $historyYear = $history->years()->create(['source' => 'system', 'student_enrollment_id' => $enrollment->id,
            'label' => '3º Ano', 'year' => '2024', 'final_result' => 'Retido por Pontos']);
        $historyComponent = $history->components()->create(['name' => 'Matemática']);
        $record = $historyComponent->records()->create(['student_academic_history_year_id' => $historyYear->id,
            'curriculum_component_id' => $component->id, 'score_label' => '6,0', 'workload_hours' => 120, 'result' => 'Retido por Pontos']);
        $request = ['format' => 'verified-legacy-approval-v1', 'confirmed_by_management' => true,
            'person_id' => $student->id, 'school_id' => $school->id, 'source' => $source, 'legacy_student_id' => 34,
            'enrollments' => [['enrollment_id' => $enrollment->id, 'reference_year' => 2024, 'source_documents' => [
                ['component_id' => $component->id, 'sha256' => str_repeat('a', 64), 'reported_average' => 6, 'reported_result' => 'aprovado'],
            ]]]];

        return [$request, $enrollment, $historyYear, $record];
    }
}
