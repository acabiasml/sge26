<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentPeriodConvalidation;
use App\Support\TechnicalGradeCompleter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TechnicalGradeCompleterTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_only_empty_technical_periods_with_maximum_or_seven_and_preserves_zero_and_other_stages(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $firstAssessment = $this->assessment($class, $component, $first);
        $firstAssessment->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => 8]);
        $firstAssessment->results()->create(['student_enrollment_id' => $students[2]->id, 'score' => 0]);
        $regular = $course->academicYear->courses()->create(['name' => 'Ensino médio', 'stage' => 'medio']);
        $regularComponent = $regular->components()->create(['name' => 'Componente regular', 'active' => true]);
        $outside = $this->assessment($class, $regularComponent, $first);
        $outside->results()->create(['student_enrollment_id' => $students[1]->id, 'score' => 10]);
        $outsideBefore = $outside->fresh()->load('results')->toArray();
        $second->assessmentRules()->create(['school_id' => $course->academicYear->school_id, 'position' => 2, 'name' => 'Avaliação 2', 'weight' => 10, 'maximum_score' => 10]);
        $secondAssessment = $this->assessment($class, $component, $second);
        $empty = $secondAssessment->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => null, 'notes' => 'Anotação anterior']);
        $recovery = $this->assessment($class, $component, $second, true);
        $recoveryBefore = $recovery->fresh()->load('results')->toArray();
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $this->assertSame(4, $plan['stats']['student_periods_to_fill']);
        $this->assertSame(2, $plan['stats']['student_periods_from_default']);
        $this->assertNull($empty->fresh()->score);
        $applied = $service->complete([$course->id], true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        foreach (DiaryAssessment::where('curriculum_component_id', $component->id)->where('academic_period_id', $second->id)->where('is_recovery', false)->get() as $assessment) {
            foreach ([8, 7, 0] as $index => $score) {
                $this->assertEquals($score, $assessment->results()->where('student_enrollment_id', $students[$index]->id)->sole()->score);
            }
        }
        $this->assertSame('Anotação anterior', $empty->fresh()->notes);
        $this->assertSame($outsideBefore, $outside->fresh()->load('results')->toArray());
        $this->assertSame($recoveryBefore, $recovery->fresh()->load('results')->toArray());
        $this->assertSame(1, DiaryAssessment::where('is_recovery', true)->count());
        $this->assertEquals(0, $firstAssessment->results()->where('student_enrollment_id', $students[2]->id)->sole()->score);
        $next = $service->complete([$course->id]);
        $this->assertSame(0, $next['stats']['student_periods_to_fill']);
        $this->assertSame(0, $next['stats']['results_to_create']);
    }

    public function test_a_period_with_any_grade_is_preserved_including_unfilled_other_regular_assessments(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $one = $this->assessment($class, $component, $first);
        $one->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => 4]);
        $two = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'is_recovery' => false, 'title' => 'Outra avaliação', 'maximum_score' => 10, 'weight' => 10]);
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $service->complete([$course->id], true, $plan['snapshot_sha256']);
        $this->assertFalse($two->results()->where('student_enrollment_id', $students[0]->id)->exists());
        $this->assertEquals(4, DiaryAssessmentResult::where('student_enrollment_id', $students[0]->id)
            ->whereHas('assessment', fn ($q) => $q->where('academic_period_id', $second->id))->sole()->score);
    }

    public function test_modular_boundaries_future_periods_and_enrollment_dates_are_respected(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $component->update(['starts_period_id' => $second->id, 'ends_period_id' => $second->id]);
        $students[0]->update(['transferred_at' => '2026-04-09']);
        $students[1]->update(['enrolled_at' => '2026-07-20']);
        $students[2]->update(['enrolled_at' => '2026-05-01']);
        $future = $course->academicYear->periods()->create(['name' => 'Futuro', 'position' => 3, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-22']);
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $this->assertSame(1, $plan['stats']['student_periods_to_fill']);
        $service->complete([$course->id], true, $plan['snapshot_sha256']);
        $this->assertDatabaseCount('diary_assessment_results', 1);
        $this->assertDatabaseHas('diary_assessment_results', ['student_enrollment_id' => $students[2]->id, 'score' => 7]);
        $this->assertFalse(DiaryAssessment::whereIn('academic_period_id', [$first->id, $future->id])->exists());
    }

    public function test_source_notes_are_scoped_to_the_same_component_and_academic_year_and_scales_are_normalized(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $assessment = $this->assessment($class, $component, $first);
        $assessment->update(['maximum_score' => 5]);
        $assessment->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => 4]);
        $another = $course->components()->create(['name' => 'Outra disciplina técnica', 'active' => true]);
        $this->assessment($class, $another, $first)->results()->create(['student_enrollment_id' => $students[1]->id, 'score' => 10]);
        $olderYear = AcademicYear::create(['school_id' => $course->academicYear->school_id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $olderPeriod = $olderYear->periods()->create(['name' => 'Ano anterior', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $this->assessment($class, $component, $olderPeriod)->results()->create(['student_enrollment_id' => $students[1]->id, 'score' => 10]);
        $second->assessmentRules()->first()->update(['maximum_score' => 20]);
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $service->complete([$course->id], true, $plan['snapshot_sha256']);
        $secondAssessment = DiaryAssessment::where('curriculum_component_id', $component->id)->where('academic_period_id', $second->id)->sole();
        $this->assertEquals(16, $secondAssessment->results()->where('student_enrollment_id', $students[0]->id)->sole()->score);
        $this->assertEquals(14, $secondAssessment->results()->where('student_enrollment_id', $students[1]->id)->sole()->score);
    }

    public function test_existing_recovery_and_origin_grades_are_preserved_and_no_new_recovery_is_launched(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $recovery = $this->assessment($class, $component, $first, true);
        $recovery->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => 9]);
        $before = $recovery->fresh()->load('results')->toArray();
        $origin = StudentPeriodConvalidation::create(['student_enrollment_id' => $students[1]->id,
            'curriculum_component_id' => $component->id, 'academic_period_id' => $first->id, 'score' => 8.5]);
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $service->complete([$course->id], true, $plan['snapshot_sha256']);
        $this->assertSame($before, $recovery->fresh()->load('results')->toArray());
        $this->assertSame(1, DiaryAssessment::where('is_recovery', true)->count());
        $this->assertEquals(8.5, $origin->fresh()->score);
        foreach ([$students[0]->id => 9, $students[1]->id => 8.5] as $enrollmentId => $score) {
            $this->assertEquals($score, DiaryAssessmentResult::where('student_enrollment_id', $enrollmentId)
                ->whereHas('assessment', fn ($q) => $q->where('academic_period_id', $second->id)->where('is_recovery', false))->sole()->score);
        }
    }

    public function test_stale_preview_and_non_technical_matrix_are_rejected_before_writing(): void
    {
        [$course, $class, $component, $first, $second, $students] = $this->scenario();
        $service = app(TechnicalGradeCompleter::class);
        $plan = $service->complete([$course->id]);
        $assessment = $this->assessment($class, $component, $first);
        $assessment->results()->create(['student_enrollment_id' => $students[0]->id, 'score' => 5]);
        try {
            $service->complete([$course->id], true, $plan['snapshot_sha256']);
            $this->fail('A alteração posterior precisa invalidar a conferência.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertDatabaseCount('diary_assessment_results', 1);
        $regular = $course->academicYear->courses()->create(['name' => 'Ensino médio', 'stage' => 'medio']);
        $this->expectExceptionMessage('não é de curso técnico');
        $service->complete([$course->id, $regular->id]);
    }

    private function assessment($class, $component, $period, bool $recovery = false): DiaryAssessment
    {
        return DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'school_assessment_rule_id' => $recovery ? null : $period->assessmentRules()->first()->id,
            'is_recovery' => $recovery, 'title' => $recovery ? 'Recuperação existente' : 'Avaliação', 'maximum_score' => 10, 'weight' => 10]);
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-02-02', 'ends_at' => '2026-12-22']);
        $first = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2026-02-02', 'ends_at' => '2026-04-10']);
        $second = $year->periods()->create(['name' => 'II Bimestre', 'position' => 2, 'starts_at' => '2026-04-13', 'ends_at' => '2026-07-08']);
        $course = $year->courses()->create(['name' => 'Técnico', 'stage' => 'tecnico', 'class_hour_minutes' => 60]);
        $component = $course->components()->create(['name' => 'Componente técnico', 'workload_hours' => 120, 'active' => true]);
        $class = $year->classes()->create(['name' => 'Turma']);
        $class->courses()->attach($course);
        $students = [];
        foreach (['A', 'B', 'Zero'] as $name) {
            $person = Person::create(['full_name' => 'Estudante '.$name]);
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
            $enrollment->courses()->attach($course);
            $students[] = $enrollment;
        }

        return [$course, $class, $component, $first, $second, $students];
    }
}
