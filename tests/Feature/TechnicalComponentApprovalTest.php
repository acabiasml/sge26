<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Support\ComponentApprovalCriteria;
use App\Support\StudentFinalResultCalculator;
use App\Support\StudentProgressSummary;
use App\Support\StudentReportCardBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalComponentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrated_technical_average_six_passes_without_demanding_24_points_or_dependency_configuration(): void
    {
        [$enrollment, $technical, $result] = $this->scenario();
        $report = app(StudentReportCardBuilder::class)->build($enrollment);
        $this->assertCount(2, $report['annualComponents']);
        $this->assertSame('aprovado', app(StudentFinalResultCalculator::class)->calculate($enrollment)['status']);
        $progress = app(StudentProgressSummary::class)->fromReport($report);
        $row = $progress['components']->firstWhere('component.id', $technical->id);
        $this->assertSame(6.0, $row['points']);
        $this->assertSame(0, $row['missing_points']);
        $this->assertSame('6.00', $result->fresh()->score);
    }

    public function test_technical_component_below_six_is_retained_even_when_other_components_have_high_scores(): void
    {
        [$enrollment, $technical, $result] = $this->scenario();
        $result->update(['score' => 5.5]);
        $calculated = app(StudentFinalResultCalculator::class)->calculate($enrollment);
        $this->assertSame('retido_por_pontos', $calculated['status']);
        $this->assertSame(5.5, $calculated['details']['failed_components'][0]['points']);
        $this->assertSame(6.0, $calculated['details']['failed_components'][0]['passing_points']);
        $this->assertSame('component_average', $calculated['details']['failed_components'][0]['grading_basis']);
    }

    public function test_missing_technical_grade_remains_pending_and_modular_points_are_not_added_to_reach_six(): void
    {
        [$enrollment, $technical, $result] = $this->scenario();
        $result->delete();
        $this->assertSame('pendente', app(StudentFinalResultCalculator::class)->calculate($enrollment)['status']);
        $summary = ['component' => $technical, 'points' => 8, 'periods' => collect([
            ['average' => ['value' => 4]], ['average' => ['value' => 4]],
        ])];
        $criteria = ComponentApprovalCriteria::evaluate($summary, 24);
        $this->assertSame(4.0, $criteria['value']);
        $this->assertFalse($criteria['passed']);
    }

    public function test_inactive_component_is_removed_from_matrix_totals_and_reports_while_source_results_remain(): void
    {
        [$enrollment, $technical, $result] = $this->scenario();
        $course = $technical->course;
        $this->assertSame(120.0, $course->calculatedWorkloadHours());
        $technical->update(['active' => false]);
        $course->refresh();
        $this->assertSame(0.0, $course->calculatedWorkloadHours());
        $this->assertTrue($course->componentsGroupedByFormationAndArea()->isEmpty());
        $this->assertSame('6.00', $result->fresh()->score);
    }

    private function scenario(): array
    {
        $school = School::create(['name' => 'Liceu']);
        $person = Person::create(['full_name' => 'Estudante']);
        foreach (['medio', 'tecnico'] as $stage) {
            $year = AcademicYear::create(['school_id' => $school->id, 'name' => $stage, 'reference_year' => 2025,
                'starts_at' => '2025-02-03', 'ends_at' => '2025-12-19', 'passing_points' => 24, 'minimum_attendance_percentage' => 75]);
            $course = $year->courses()->create(['name' => $stage, 'stage' => $stage, 'class_hour_minutes' => 60]);
            $component = $course->components()->create(['name' => $stage === 'medio' ? 'Matemática' : 'Desenho Técnico I', 'active' => true, 'workload_hours' => 120]);
            $class = $year->classes()->create(['name' => $stage]);
            $class->courses()->attach($course);
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-03', 'status' => 'matriculado']);
            $enrollment->courses()->attach($course);
            $count = $stage === 'medio' ? 4 : 1;
            for ($i = 1; $i <= $count; $i++) {
                $period = $year->periods()->create(['name' => 'Período '.$i, 'position' => $i, 'starts_at' => '2025-02-03', 'ends_at' => '2025-12-19']);
                $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                    'academic_period_id' => $period->id, 'title' => 'Avaliação', 'maximum_score' => 10, 'weight' => 10]);
                $result = $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $stage === 'medio' ? 9 : 6]);
                $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                    'academic_period_id' => $period->id, 'class_date' => '2025-03-0'.$i, 'lesson_count' => 1]);
                $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
            }
            if ($stage === 'medio') {
                $regular = $enrollment;
                $regularCourse = $course;
            } else {
                $regularCourse->technicalItineraries()->attach($course);
            }
        }

        return [$regular, $component, $result];
    }
}
