<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Support\FirstPeriodReclassificationRepair;
use App\Support\StudentReportCardBuilder;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirstPeriodReclassificationRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repairs_effective_date_and_all_components_and_carries_hours_without_changing_grades_or_duplicating_attendance(): void
    {
        [$request, $origin, $destination, $year] = $this->scenario();
        $service = app(FirstPeriodReclassificationRepair::class);
        $scores = $destination->assessmentResults()->get()->toArray();
        $beforeEntries = $origin->attendanceEntries()->count() + $destination->attendanceEntries()->count();
        $plan = $service->reconcile($request);
        $this->assertNull($origin->fresh()->reclassified_at);
        $this->assertSame($beforeEntries, $origin->attendanceEntries()->count() + $destination->attendanceEntries()->count());
        $this->assertSame('2025-04-01', $plan['effective_at']);
        $this->assertSame(2, $plan['outside_window_entries_removed']);
        $this->assertSame(1, $plan['boarding_absences_aligned']);
        $applied = $service->reconcile($request, true, $plan['snapshot_sha256']);
        $this->assertSame('2025-03-31', $origin->fresh()->attendanceEndsAt());
        $this->assertSame('2025-04-01', $destination->fresh()->enrolled_at->toDateString());
        $this->assertNull($destination->fresh()->attendanceEndsAt());
        $this->assertSame($origin->id, $destination->fresh()->reclassified_from_enrollment_id);
        $this->assertSame($scores, $destination->assessmentResults()->get()->toArray());
        $report = app(StudentReportCardBuilder::class)->build($destination->fresh());
        $this->assertSame(240, $report['annualAttendance']['diary_attended_minutes']);
        $this->assertSame(2, $report['annualAttendance']['absent']);
        $this->assertCount(1, $report['attendanceCreditComponents']);
        $this->assertSame('Projeto de Vida', $report['attendanceCreditComponents'][0]['component']->name);
        $this->assertSame(0, $report['attendanceCreditComponents'][0]['planned_workload_hours']);
        $this->assertSame(8.0, $report['periodReports'][0]['components'][0]['average']['value']);
        $this->assertSame('aprovado', $destination->fresh()->final_result_status);
        $history = app(UnifiedStudentHistorySynchronizer::class)->synchronize($destination->student, $year->school_id, $destination->person_id, 'medio');
        $historyYear = $history->years->firstWhere('student_enrollment_id', $destination->id);
        $creditRecord = $historyYear->records->firstWhere('score_label', 'AP');
        $this->assertNotNull($creditRecord);
        $this->assertSame(0.0, (float) $creditRecord->workload_hours);
        $this->assertSame(1.0, (float) $creditRecord->completed_workload_hours);
        $again = $service->reconcile($request);
        $this->assertSame(0, $again['enrollments_to_update'] + $again['outside_window_entries_removed'] + $again['boarding_absences_aligned'] + $again['credits_to_update']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_destination_reclassification_date_is_not_an_exit_date_and_transfer_date_remains_inclusive(): void
    {
        $e = new StudentEnrollment(['status' => 'matriculado', 'enrolled_at' => '2025-04-01', 'reclassified_at' => '2025-04-01']);
        $this->assertNull($e->attendanceEndsAt());
        $e->status = 'transferido'; $e->transferred_at = '2025-09-10';
        $this->assertSame('2025-09-10', $e->attendanceEndsAt());
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'closed_at' => '2026-01-01']);
        $first = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-03-31']);
        $second = $year->periods()->create(['name' => 'II Bimestre', 'position' => 2, 'starts_at' => '2025-04-01', 'ends_at' => '2025-06-30']);
        $person = Person::create(['full_name' => 'Estudante']);
        $rows = [];
        foreach ([1, 2] as $grade) {
            $course = $year->courses()->create(['name' => $grade.'º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
            $component = $course->components()->create(['name' => 'Matemática', 'active' => true, 'workload_hours' => 120]);
            $class = $year->classes()->create(['name' => $grade.'º Ano']); $class->courses()->attach($course);
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'status' => $grade === 1 ? 'reclassificado' : 'matriculado',
                'enrolled_at' => '2025-02-01', 'final_result_status' => $grade === 1 ? 'reclassificado' : 'aprovado']);
            $enrollment->courses()->attach($course);
            $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $first->id, 'title' => 'Média']);
            $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $grade === 1 ? 5 : 8]);
            $rows[] = [$enrollment, $class, $component];
        }
        [$origin, $from, $math] = $rows[0]; [$destination, $to, $targetMath] = $rows[1];
        $project = $math->course->components()->create(['name' => 'Projeto de Vida', 'active' => true, 'workload_hours' => 40]);
        $call = function ($enrollment, $class, $component, $period, $date, $lessons, $attended): void {
            $record = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'class_date' => $date, 'lesson_count' => $lessons]);
            $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => $attended ? 'presente' : 'falta', 'attended_lessons' => $attended]);
        };
        $call($origin, $from, $math, $first, '2025-03-01', 2, 2);
        $call($origin, $from, $project, $first, '2025-03-01', 1, 1);
        $call($origin, $from, $math, $first, '2025-03-02', 1, 0);
        $call($origin, $from, $project, $first, '2025-03-02', 1, 1);
        $call($origin, $from, $math, $second, '2025-04-02', 1, 1);
        $call($destination, $to, $targetMath, $first, '2025-03-03', 1, 1);
        $call($destination, $to, $targetMath, $second, '2025-04-02', 1, 1);
        $request = ['format' => 'first-period-reclassification-repair-v1', 'confirmed_by_management' => true,
            'school_id' => $school->id, 'reference_year' => 2025, 'origin_enrollment_id' => $origin->id, 'destination_enrollment_id' => $destination->id];

        return [$request, $origin, $destination, $year];
    }
}
