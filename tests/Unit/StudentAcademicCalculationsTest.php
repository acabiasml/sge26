<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use App\Support\AttendanceSummaryCalculator;
use App\Support\StudentFinalResultCalculator;
use App\Support\StudentReportCardBuilder;
use Tests\TestCase;

class StudentAcademicCalculationsTest extends TestCase
{
    public function test_completed_hours_apply_relative_attendance_to_planned_hours(): void
    {
        $calculator = new AttendanceSummaryCalculator;
        $this->assertSame(67.0, $calculator->completedWorkloadHours(67, $calculator->fromTotals(8, 8, 0)));
        $this->assertSame(60.9, $calculator->completedWorkloadHours(67, ['percentage' => 90.9]));
        $this->assertSame(144.0, $calculator->completedWorkloadHours(160, $calculator->fromTotals(10, 7, 2)));
        $this->assertSame(0.0, $calculator->completedWorkloadHours(160, ['percentage' => 0]));
        $this->assertNull($calculator->completedWorkloadHours(160, ['percentage' => null]));

        $record = new StudentAcademicHistoryRecord(['workload_hours' => 160, 'frequency_percentage' => 90]);
        $this->assertSame(144.0, $record->completedWorkloadHours(new StudentAcademicHistoryYear(['source' => 'system'])));
        $this->assertSame(160.0, $record->completedWorkloadHours(new StudentAcademicHistoryYear(['source' => 'external'])));
    }

    public function test_closed_enrollment_does_not_display_as_enrolled_or_in_progress(): void
    {
        $year = new AcademicYear(['closed_at' => now()]);
        $class = new SchoolClass;
        $class->setRelation('academicYear', $year);
        $enrollment = new StudentEnrollment(['status' => StudentEnrollment::STATUS_ENROLLED, 'final_result_status' => StudentEnrollment::FINAL_PENDING]);
        $enrollment->setRelation('schoolClass', $class);
        $this->assertSame('Encerrada', $enrollment->statusLabel());
        $this->assertSame('Pendente', $enrollment->finalResultLabel());
        $enrollment->status = StudentEnrollment::STATUS_TRANSFERRED;
        $this->assertSame('Transferido', $enrollment->statusLabel());
    }

    public function test_missing_dependency_rule_is_not_treated_as_zero_and_confirmed_limits_are_respected(): void
    {
        foreach ([
            [null, 24.0, StudentEnrollment::FINAL_PENDING],
            [0, 24.0, StudentEnrollment::FINAL_RETAINED_POINTS],
            [2, 24.0, StudentEnrollment::FINAL_RETAINED_POINTS],
            [3, 24.0, StudentEnrollment::FINAL_DEPENDENCY],
            [2, 22.5, StudentEnrollment::FINAL_DEPENDENCY],
        ] as [$limit, $passingPoints, $expected]) {
            $school = new School(['dependency_component_limit' => $limit]);
            $school->setRelation('academicCriteria', collect());
            $year = new AcademicYear(['ends_at' => '2025-12-31']);
            $year->setRelation('school', $school);
            $class = new SchoolClass;
            $class->setRelation('academicYear', $year);
            $enrollment = new StudentEnrollment(['status' => StudentEnrollment::STATUS_ENROLLED]);
            $enrollment->setRelation('schoolClass', $class);
            $builder = $this->createMock(StudentReportCardBuilder::class);
            $builder->method('build')->willReturn([
                'academicYear' => $year, 'passingPoints' => $passingPoints, 'minimumAttendance' => 75,
                'annualAttendance' => ['percentage' => 94.3],
                'annualComponents' => collect([21, 22.5, 23])->map(fn ($points) => [
                    'component' => new CurriculumComponent(['name' => 'Componente']),
                    'points' => $points, 'complete_periods' => 4, 'total_periods' => 4,
                ]),
            ]);
            $result = (new StudentFinalResultCalculator($builder))->calculate($enrollment);
            $this->assertSame($expected, $result['status']);
            if ($passingPoints === 22.5) {
                $this->assertCount(1, $result['details']['failed_components']);
            }
        }
    }
}
