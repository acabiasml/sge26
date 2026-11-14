<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\StudentPeriodConvalidation;
use App\Support\SchoolDiaryCompletionAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolDiaryCompletionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_double_lessons_and_zero_grades_without_exporting_student_names_or_scores(): void
    {
        [$school, $class, $course, $component, $period, $student] = $this->scenario();
        $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-10', 'lesson_count' => 2]);
        DiaryContent::create($call->only(['school_class_id', 'curriculum_component_id', 'academic_period_id', 'class_date']) + ['content' => 'Equações']);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'title' => 'Média']);
        $assessment->results()->create(['student_enrollment_id' => $student->id, 'score' => 0]);
        $report = app(SchoolDiaryCompletionAudit::class)->forSchool($school);
        $row = $report['diaries'][0];
        $this->assertSame(2, $row['content_lessons']);
        $this->assertSame(50.0, $row['content_percentage_to_date']);
        $this->assertTrue($row['below_90_content']);
        $this->assertFalse($row['no_scores']);
        $this->assertSame(100.0, $row['grade_coverage']['percentage']);
        $this->assertStringNotContainsString('Nome restrito', json_encode($report));
        $this->assertArrayNotHasKey('score', $row);
    }

    public function test_empty_html_and_absent_assessments_are_missing_and_do_not_disappear_from_the_denominator(): void
    {
        [$school, $class, $course, $component, $period] = $this->scenario();
        DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-10', 'content' => '<p>&nbsp;<br></p>']);
        $row = app(SchoolDiaryCompletionAudit::class)->forSchool($school)['diaries'][0];
        $this->assertTrue($row['neither_content_nor_scores']);
        $this->assertSame(1, $row['grade_coverage']['expected']);
        $this->assertSame(0.0, $row['grade_coverage']['percentage']);
        $this->assertTrue($row['below_90_grades']);
    }

    public function test_transfer_and_unfinished_periods_are_not_counted_as_missing_final_grades_and_origin_grades_are_identified(): void
    {
        [$school, $class, $course, $component, $period, $student] = $this->scenario();
        $period->academicYear->periods()->create(['name' => 'Futuro', 'position' => 2, 'starts_at' => '2027-01-01', 'ends_at' => '2027-03-01']);
        $student->update(['status' => 'transferido', 'transferred_at' => '2025-03-15']);
        $row = app(SchoolDiaryCompletionAudit::class)->forSchool($school)['diaries'][0];
        $this->assertSame(0, $row['grade_coverage']['expected']);
        $this->assertNull($row['grade_coverage']['percentage']);
        $this->assertFalse($row['below_90_grades']);
        $student->update(['status' => 'matriculado', 'transferred_at' => null]);
        StudentPeriodConvalidation::create(['student_enrollment_id' => $student->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'score' => 0]);
        $row = app(SchoolDiaryCompletionAudit::class)->forSchool($school)['diaries'][0];
        $this->assertTrue($row['no_scores']);
        $this->assertSame(1, $row['origin_grade_entries']);
        $this->assertSame(100.0, $row['grade_coverage']['percentage']);
    }

    public function test_inactive_components_are_excluded_and_missing_teacher_assignment_does_not_hide_an_offered_diary(): void
    {
        [$school, $class, $course] = $this->scenario();
        SchoolClassComponent::query()->delete();
        $course->components()->create(['name' => 'Biologia arquivada', 'active' => false]);
        $report = app(SchoolDiaryCompletionAudit::class)->forSchool($school);
        $this->assertCount(1, $report['diaries']);
        $this->assertNull($report['diaries'][0]['assignment_id']);
        $this->assertCount(1, $report['excluded_components']);
        $this->assertSame('inactive_component', $report['excluded_components'][0]['reason']);
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 14));
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'closed_at' => '2026-01-01']);
        $period = $year->periods()->create(['name' => 'Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-03-31']);
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $component = $course->components()->create(['name' => 'Matemática', 'workload_hours' => 4, 'active' => true]);
        $class = $year->classes()->create(['name' => '1º Ano', 'active' => false]);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Nome restrito']);
        $student = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $student->courses()->attach($course);
        SchoolClassComponent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'active' => true]);

        return [$school, $class, $course, $component, $period, $student];
    }
}
