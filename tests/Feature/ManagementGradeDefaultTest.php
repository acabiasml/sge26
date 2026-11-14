<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentPeriodConvalidation;
use App\Support\ManagementGradeDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ManagementGradeDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaces_only_zero_and_missing_grades_during_enrollment_and_preserves_positive_values_and_optional_recovery(): void
    {
        [$school, $year, $class, $component, $empty, $first, $second, $active, $transferred, $future, $a, $b] = $this->scenario();
        $positive = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'title' => 'Outra avaliação']);
        $positiveResult = $positive->results()->create(['student_enrollment_id' => $active->id, 'score' => 4]);
        $recovery = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'title' => 'Recuperação', 'is_recovery' => true]);
        $recoveryZero = $recovery->results()->create(['student_enrollment_id' => $active->id, 'score' => 0]);
        $service = app(ManagementGradeDefault::class);
        $before = DiaryAssessmentResult::count();
        $plan = $service->complete($school->id);
        $this->assertSame($before, DiaryAssessmentResult::count());
        $this->assertSame('0.00', $a->results()->where('student_enrollment_id', $active->id)->sole()->score);
        $applied = $service->complete($school->id, true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        $this->assertSame('6.00', $a->results()->where('student_enrollment_id', $active->id)->sole()->score);
        $this->assertSame('6.00', $b->results()->where('student_enrollment_id', $active->id)->sole()->score);
        $this->assertSame('6.00', $a->results()->where('student_enrollment_id', $transferred->id)->sole()->score);
        $this->assertSame('0.00', $b->results()->where('student_enrollment_id', $transferred->id)->sole()->score);
        $this->assertSame('0.00', $a->results()->where('student_enrollment_id', $future->id)->sole()->score);
        $this->assertSame('4.00', $positiveResult->fresh()->score);
        $this->assertSame('6.00', $recoveryZero->fresh()->score);
        $this->assertFalse($recovery->results()->where('student_enrollment_id', $transferred->id)->exists());
        $this->assertStringContainsString('decisão administrativa', $recoveryZero->fresh()->notes);
        $this->assertSame('aprovado', $active->fresh()->final_result_status);
        $new = DiaryAssessment::where('curriculum_component_id', $empty->id)->firstOrFail();
        $this->assertNull($new->teacher_person_id);
        $this->assertNull($new->assessment_date);
        $this->assertSame(ManagementGradeDefault::SOURCE, $new->legacy_source);
        $this->assertTrue(AuditLog::where('metadata->operation', ManagementGradeDefault::SOURCE)->exists());
        $again = $service->complete($school->id);
        foreach (['zero_results_to_replace', 'null_results_to_fill', 'missing_results_to_create', 'assessments_to_create', 'origin_grades_to_fill'] as $stat) {
            $this->assertSame(0, $again['stats'][$stat], $stat);
        }
        $this->assertTrue($service->complete($school->id, true, $again['snapshot_sha256'])['idempotent']);
    }

    public function test_preserves_valid_origin_grades_and_uses_six_on_the_normalized_scale(): void
    {
        [$school, $year, $class, $component, $empty, $first, $second, $active] = $this->scenario();
        $year->periods->each(fn ($p) => $p->assessmentRules()->update(['maximum_score' => 20]));
        $origin = StudentPeriodConvalidation::create(['student_enrollment_id' => $active->id, 'academic_period_id' => $first->id,
            'curriculum_component_id' => $empty->id, 'score' => 8]);
        $zeroOrigin = StudentPeriodConvalidation::create(['student_enrollment_id' => $active->id, 'academic_period_id' => $second->id,
            'curriculum_component_id' => $component->id, 'score' => 0]);
        $service = app(ManagementGradeDefault::class);
        $plan = $service->complete($school->id);
        $service->complete($school->id, true, $plan['snapshot_sha256']);
        $this->assertSame('8.0', $origin->fresh()->score);
        $this->assertSame('6.0', $zeroOrigin->fresh()->score);
        $new = DiaryAssessment::where('curriculum_component_id', $empty->id)->where('academic_period_id', $second->id)->sole();
        $this->assertSame('20.00', $new->maximum_score);
        $this->assertSame('12.00', $new->results()->where('student_enrollment_id', $active->id)->sole()->score);
        $firstEmpty = DiaryAssessment::where('curriculum_component_id', $empty->id)->where('academic_period_id', $first->id)->sole();
        $this->assertFalse($firstEmpty->results()->where('student_enrollment_id', $active->id)->exists());
    }

    public function test_other_schools_years_inactive_components_and_stale_snapshots_are_protected(): void
    {
        [$school, $year, $class, $component, $empty, $first, $second, $active, $transferred, $future, $a] = $this->scenario();
        $inactive = $component->course->components()->create(['name' => 'Biologia removida', 'active' => false]);
        $excluded = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $inactive->id,
            'academic_period_id' => $first->id, 'title' => 'Registro preservado']);
        $excludedScore = $excluded->results()->create(['student_enrollment_id' => $active->id, 'score' => 0]);
        $futureYear = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20']);
        $futureClass = $futureYear->classes()->create(['name' => '2026']);
        $futureCourse = $futureYear->courses()->create(['name' => '2026', 'stage' => 'medio']);
        $futureComponent = $futureCourse->components()->create(['name' => 'Matemática', 'active' => true]);
        $futureClass->courses()->attach($futureCourse);
        $futureEnrollment = $futureClass->enrollments()->create(['person_id' => $active->person_id, 'status' => 'matriculado', 'enrolled_at' => '2026-02-01']);
        $futureEnrollment->courses()->attach($futureCourse);
        $futurePeriod = $futureYear->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2026-02-01', 'ends_at' => '2026-04-30']);
        $futureAssessment = DiaryAssessment::create(['school_class_id' => $futureClass->id, 'curriculum_component_id' => $futureComponent->id,
            'academic_period_id' => $futurePeriod->id, 'title' => '2026']);
        $futureScore = $futureAssessment->results()->create(['student_enrollment_id' => $futureEnrollment->id, 'score' => 0]);
        $other = School::create(['name' => 'Outra escola']);
        $service = app(ManagementGradeDefault::class);
        try {
            $service->complete($other->id);
            $this->fail('Escola fora do recorte.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Liceu', $e->getMessage());
        }
        $plan = $service->complete($school->id);
        $a->results()->where('student_enrollment_id', $active->id)->update(['score' => 9]);
        try {
            $service->complete($school->id, true, $plan['snapshot_sha256']);
            $this->fail('Fotografia desatualizada.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mudaram', $e->getMessage());
        }
        $plan = $service->complete($school->id);
        $service->complete($school->id, true, $plan['snapshot_sha256']);
        $this->assertSame('0.00', $excludedScore->fresh()->score);
        $this->assertSame('0.00', $futureScore->fresh()->score);
        $this->assertSame('9.00', $a->results()->where('student_enrollment_id', $active->id)->sole()->score);
    }

    public function test_reclassification_receives_origin_grade_without_creating_a_result_before_its_enrollment(): void
    {
        [$school, $year, $class, $component, $empty, $first, $second, $origin, $transferred, $future, $a, $b] = $this->scenario();
        $origin->update(['status' => 'reclassificado', 'reclassified_at' => '2025-05-01']);
        $b->results()->where('student_enrollment_id', $origin->id)->update(['score' => 0]);
        $course = $year->courses()->create(['name' => '2º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $target = $course->components()->create(['name' => 'Matemática', 'active' => true]);
        $to = $year->classes()->create(['name' => '2º Ano']);
        $to->courses()->attach($course);
        $destination = $to->enrollments()->create(['person_id' => $origin->person_id, 'enrolled_at' => '2025-05-01',
            'reclassified_at' => '2025-05-01', 'reclassified_from_enrollment_id' => $origin->id, 'status' => 'matriculado']);
        $destination->courses()->attach($course);
        $service = app(ManagementGradeDefault::class);
        $plan = $service->complete($school->id);
        $service->complete($school->id, true, $plan['snapshot_sha256']);
        $this->assertSame('0.00', $b->results()->where('student_enrollment_id', $origin->id)->sole()->score);
        $this->assertSame('6.0', $destination->periodConvalidations()->where('academic_period_id', $first->id)->where('curriculum_component_id', $target->id)->sole()->score);
        $this->assertFalse($destination->assessmentResults()->whereHas('assessment', fn ($q) => $q->where('academic_period_id', $first->id))->exists());
        $this->assertSame('6.00', $destination->assessmentResults()->whereHas('assessment', fn ($q) => $q->where('academic_period_id', $second->id))->sole()->score);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Liceu', 'legacy_source' => 'beaba']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $first = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-04-30']);
        $second = $year->periods()->create(['name' => 'II Bimestre', 'position' => 2, 'starts_at' => '2025-05-01', 'ends_at' => '2025-06-30']);
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $component = $course->components()->create(['name' => 'Matemática', 'active' => true]);
        $empty = $course->components()->create(['name' => 'Arte', 'active' => true]);
        $class = $year->classes()->create(['name' => '1º Ano']);
        $class->courses()->attach($course);
        $enrollments = [];
        foreach ([['Estudante', 'matriculado', '2025-02-01', null], ['Transferido', 'transferido', '2025-02-01', '2025-03-15'], ['Ingresso posterior', 'matriculado', '2025-07-01', null]] as [$name, $status, $start, $end]) {
            $person = Person::create(['full_name' => $name]);
            $e = $class->enrollments()->create(['person_id' => $person->id, 'status' => $status, 'enrolled_at' => $start,
                'transferred_at' => $end, 'final_result_status' => 'aprovado']);
            $e->courses()->attach($course);
            $enrollments[] = $e;
        }
        [$active, $transferred, $future] = $enrollments;
        $a = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'title' => 'Nota bimestral']);
        $b = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $second->id, 'title' => 'Nota bimestral']);
        foreach ($enrollments as $e) {
            $a->results()->create(['student_enrollment_id' => $e->id, 'score' => 0]);
        }
        $b->results()->create(['student_enrollment_id' => $active->id, 'score' => null]);
        $b->results()->create(['student_enrollment_id' => $transferred->id, 'score' => 0]);

        return [$school, $year, $class, $component, $empty, $first, $second, $active, $transferred, $future, $a, $b];
    }
}
