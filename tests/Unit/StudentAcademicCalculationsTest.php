<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
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
    public function test_completed_hours_use_only_recorded_presences_in_real_hours(): void
    {
        $calculator = new AttendanceSummaryCalculator;
        $record = new DiaryAttendanceRecord(['class_date' => '2026-03-11', 'lesson_count' => 8]);
        $record->setRelation('entries', collect([new DiaryAttendanceEntry(['status' => 'presente', 'attended_lessons' => 8])]));
        $summary = $calculator->summarize(collect([$record]), collect(), 50);
        $this->assertSame(100.0, $summary['percentage']);
        $this->assertSame(6.67, $calculator->completedWorkloadHours($summary));
        $this->assertNull($calculator->completedWorkloadHours(['percentage' => 100]));
        $this->assertSame(0.0, $calculator->completedWorkloadHours($calculator->summarize(collect(), collect(), 50)));
        $this->assertNull($calculator->completedWorkloadHours($calculator->summarize(collect([$record]), collect(), 0)));

        $record->entries->first()->fill(['status' => 'justificada', 'attended_lessons' => 2]);
        $summary = $calculator->summarize(collect([$record]), collect(), 50);
        $this->assertSame(100.0, $summary['percentage']);
        $this->assertSame(1.67, $calculator->completedWorkloadHours($summary));
        $record->entries->first()->attended_lessons = 0;
        $this->assertSame(0.0, $calculator->completedWorkloadHours($calculator->summarize(collect([$record]), collect(), 50)));

        $record = new StudentAcademicHistoryRecord(['workload_hours' => 160, 'frequency_percentage' => 90, 'completed_workload_hours' => 1.67]);
        $this->assertSame(1.67, $record->completedWorkloadHours(new StudentAcademicHistoryYear(['source' => 'system'])));
        $this->assertSame(160.0, $record->completedWorkloadHours(new StudentAcademicHistoryYear(['source' => 'external'])));
    }

    public function test_attendance_aggregation_keeps_original_diary_minutes_separate_from_imported_attendance(): void
    {
        $calculator = new AttendanceSummaryCalculator;
        $summary = $calculator->aggregate(collect([
            $calculator->fromTotals(1, 1, 0) + ['diary_attended_minutes' => 40],
            $calculator->fromTotals(2, 2, 0) + ['diary_attended_minutes' => 100],
            $calculator->fromTotals(200, 200, 0),
        ]));
        $this->assertSame(100.0, $summary['percentage']);
        $this->assertSame(140, $summary['diary_attended_minutes']);
        $this->assertSame(2.33, $calculator->completedWorkloadHours($summary));
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
