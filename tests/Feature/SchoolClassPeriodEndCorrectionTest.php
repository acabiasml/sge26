<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\Person;
use App\Models\School;
use App\Support\SchoolClassPeriodEndCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolClassPeriodEndCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrects_only_confirmed_class_ends_and_preserves_scores_and_enrollment_results(): void
    {
        [$school, $class, $second, $fourth, $result] = $this->scenario();
        $service = app(SchoolClassPeriodEndCorrection::class);
        $before = $result->fresh()->getAttributes();
        $plan = $service->correct($school->id, 2025, [$class->id], $second->id, $fourth->id);
        $this->assertSame(1, $plan['classes_to_update']);
        $this->assertSame($second->id, $class->fresh()->ends_period_id);
        $applied = $service->correct($school->id, 2025, [$class->id], $second->id, $fourth->id, true, $plan['snapshot_sha256']);
        $this->assertSame($fourth->id, $class->fresh()->ends_period_id);
        $this->assertSame($before, $result->fresh()->getAttributes());
        $again = $service->correct($school->id, 2025, [$class->id], $second->id, $fourth->id);
        $this->assertSame(0, $again['classes_to_update']);
        $this->assertSame($plan['protected_sha256'], $again['protected_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_rejects_changes_since_the_preview_and_other_school_scope(): void
    {
        [$school, $class, $second, $fourth, $result] = $this->scenario();
        $service = app(SchoolClassPeriodEndCorrection::class);
        $plan = $service->correct($school->id, 2025, [$class->id], $second->id, $fourth->id);
        $result->update(['score' => 7]);
        try {
            $service->correct($school->id, 2025, [$class->id], $second->id, $fourth->id, true, $plan['snapshot_sha256']);
            $this->fail('A nota alterada deve invalidar a conferência.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertSame($second->id, $class->fresh()->ends_period_id);
        $this->expectExceptionMessage('fora da correção');
        $service->correct($school->id + 1, 2025, [$class->id], $second->id, $fourth->id);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'closed_at' => '2026-01-01']);
        $second = $year->periods()->create(['name' => 'II Bimestre', 'position' => 2, 'starts_at' => '2025-04-01', 'ends_at' => '2025-06-30']);
        $fourth = $year->periods()->create(['name' => 'IV Bimestre', 'position' => 4, 'starts_at' => '2025-10-01', 'ends_at' => '2025-12-20']);
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => 'medio']);
        $component = $course->components()->create(['name' => 'Matemática']);
        $class = $year->classes()->create(['name' => '1º Ano', 'ends_period_id' => $second->id]);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Estudante']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'status' => 'matriculado', 'final_result_status' => 'aprovado']);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $fourth->id, 'title' => 'Média']);
        $result = $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 6]);

        return [$school, $class, $second, $fourth, $result];
    }
}
