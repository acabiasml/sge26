<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Support\EnrollmentNonRenewalCorrection;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EnrollmentNonRenewalCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrects_provisional_transfer_to_non_renewal_preserving_2025_completion_grades_and_attendance(): void
    {
        [$request, $regular, $technical, $grade] = $this->scenario();
        $service = app(EnrollmentNonRenewalCorrection::class);
        $plan = $service->reconcile($request);
        $this->assertSame(2, $plan['enrollments_to_update']);
        $this->assertSame('transferido', $technical->fresh()->status);
        $applied = $service->reconcile($request, true, $plan['snapshot_sha256']);
        $technical->refresh();
        $regular->refresh();
        $this->assertSame('nao_renovado', $technical->status);
        $this->assertSame('Não renovada', $technical->statusLabel());
        $this->assertSame('nao_renovado', $technical->final_result_status);
        $this->assertSame('2025-12-31', $technical->attendanceEndsAt());
        $this->assertTrue($technical->includesAttendanceDate(Carbon::parse('2025-12-19')));
        $this->assertFalse($technical->includesAttendanceDate(Carbon::parse('2026-01-01')));
        $this->assertNull($technical->transferred_at);
        $this->assertArrayNotHasKey('transfer_date_pending', $technical->legacy_metadata);
        $this->assertArrayNotHasKey('confirmed_transfer_year', $technical->legacy_metadata);
        $this->assertSame('matriculado', $regular->status);
        $this->assertSame('aprovado', $regular->final_result_status);
        $this->assertStringContainsString('Observação anterior.', $regular->notes);
        $this->assertStringContainsString('Concluiu o ano letivo de 2025 e não iniciou 2026', $regular->notes);
        $this->assertSame('7.50', $grade->fresh()->score);
        $this->assertDatabaseCount('diary_attendance_entries', 1);
        $this->assertSame($plan['protected_sha256'], $service->reconcile($request)['protected_sha256']);
        $this->assertSame(0, $service->reconcile($request)['enrollments_to_update']);
        $this->assertSame('nao_renovado', app(StudentFinalResultCalculator::class)->calculate($technical)['status']);
        $this->assertFalse($technical->final_result_details['technical_course_completion_asserted']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_rejects_stale_grade_snapshot_without_changing_status(): void
    {
        [$request, $regular, $technical, $grade] = $this->scenario();
        $service = app(EnrollmentNonRenewalCorrection::class);
        $plan = $service->reconcile($request);
        $grade->update(['score' => 8]);
        try {
            $service->reconcile($request, true, $plan['snapshot_sha256']);
            $this->fail('A edição posterior precisa invalidar a simulação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertSame('transferido', $technical->fresh()->status);
        $this->assertSame('Observação anterior.', $regular->fresh()->notes);
    }

    public function test_rejects_existing_real_transfer_or_any_2026_attendance(): void
    {
        [$request, $regular, $technical] = $this->scenario();
        $technical->update(['transferred_at' => '2026-02-03']);
        $service = app(EnrollmentNonRenewalCorrection::class);
        try {
            $service->reconcile($request);
            $this->fail('Não pode substituir uma transferência com data real.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('outra movimentação', $error->getMessage());
        }
        $technical->update(['transferred_at' => null]);
        $call = DiaryAttendanceRecord::where('school_class_id', $technical->school_class_id)->sole();
        $call->update(['class_date' => '2026-02-03']);
        $this->expectExceptionMessage('frequência posterior');
        $service->reconcile($request);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $person = Person::create(['full_name' => 'Estudante']);
        $enrollments = [];
        foreach (['medio', 'tecnico'] as $stage) {
            $year = AcademicYear::create(['school_id' => $school->id, 'name' => $stage, 'reference_year' => 2025,
                'starts_at' => '2025-02-03', 'ends_at' => $stage === 'tecnico' ? '2026-12-22' : '2025-12-19']);
            $period = $year->periods()->create(['name' => 'I', 'position' => 1, 'starts_at' => '2025-02-03', 'ends_at' => '2025-12-19']);
            $course = $year->courses()->create(['name' => $stage, 'stage' => $stage, 'class_hour_minutes' => 60]);
            $class = $year->classes()->create(['name' => $stage]);
            $class->courses()->attach($course);
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-19',
                'status' => $stage === 'tecnico' ? 'transferido' : 'matriculado',
                'final_result_status' => $stage === 'tecnico' ? 'transferido' : 'aprovado',
                'notes' => $stage === 'tecnico' ? 'Transferência em 2026 com data pendente.' : 'Observação anterior.',
                'legacy_metadata' => $stage === 'tecnico' ? ['transfer_date_pending' => true, 'confirmed_transfer_year' => 2026] : []]);
            $enrollment->courses()->attach($course);
            $enrollments[] = $enrollment;
        }
        $component = $course->components()->create(['name' => 'Desenho', 'active' => true]);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'title' => 'Avaliação', 'maximum_score' => 10, 'weight' => 10]);
        $grade = $assessment->results()->create(['student_enrollment_id' => $enrollments[1]->id, 'score' => 7.5]);
        $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-12-19', 'lesson_count' => 2]);
        $call->entries()->create(['student_enrollment_id' => $enrollments[1]->id, 'status' => 'presente', 'attended_lessons' => 2]);
        $request = ['format' => 'enrollment-non-renewal-v1', 'user_confirmed_completion_and_no_next_year_enrollment' => true,
            'school_id' => $school->id, 'completed_reference_year' => 2025, 'students' => [['person_id' => $person->id,
                'regular_enrollment_id' => $enrollments[0]->id, 'technical_enrollment_id' => $enrollments[1]->id]]];

        return [$request, ...$enrollments, $grade];
    }
}
