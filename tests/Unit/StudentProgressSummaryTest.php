<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\CurriculumComponent;
use App\Support\StudentProgressSummary;
use PHPUnit\Framework\TestCase;

class StudentProgressSummaryTest extends TestCase
{
    public function test_it_calculates_remaining_hours_from_effective_absences_and_planned_workload(): void
    {
        $course = new AcademicCourse(['name' => 'Ensino Médio', 'class_hour_minutes' => 50]);
        $component = new CurriculumComponent(['name' => 'Matemática', 'workload_hours' => 160]);
        $component->setRelation('course', $course);
        $attendance = ['lessons' => 10, 'attended' => 7, 'absent' => 3, 'justified' => 2, 'effective_attended' => 9, 'percentage' => 90.0];
        $report = [
            'passingPoints' => 60,
            'minimumAttendance' => 75,
            'annualAttendance' => $attendance,
            'annualComponents' => collect([[
                'component' => $component,
                'points' => 25,
                'complete_periods' => 1,
                'total_periods' => 4,
                'periods' => collect([['average' => ['value' => 25]]]),
                'attendance' => $attendance,
            ]]),
        ];

        $summary = (new StudentProgressSummary)->fromReport($report);

        $this->assertSame(2400, $summary['absence_limit_minutes']);
        $this->assertSame(3, $summary['raw_absences']);
        $this->assertSame(2, $summary['justified_absences']);
        $this->assertSame(150, $summary['raw_absence_minutes']);
        $this->assertSame(100, $summary['justified_minutes']);
        $this->assertSame(50, $summary['absence_minutes']);
        $this->assertSame(2350, $summary['remaining_absence_minutes']);
        $this->assertSame(35.0, $summary['components'][0]['missing_points']);
        $this->assertSame(1, $summary['components'][0]['effective_absences']);
    }

    public function test_active_enrollment_without_final_result_is_labeled_cursando(): void
    {
        $enrollment = new \App\Models\StudentEnrollment(['status' => \App\Models\StudentEnrollment::STATUS_ENROLLED]);
        $this->assertSame('Cursando', $enrollment->finalResultLabel());

        $enrollment->final_result_status = \App\Models\StudentEnrollment::FINAL_PENDING;
        $this->assertSame('Cursando', $enrollment->finalResultLabel());

        $enrollment->final_result_status = \App\Models\StudentEnrollment::FINAL_APPROVED;
        $this->assertSame('Aprovado', $enrollment->finalResultLabel());

        $enrollment->status = \App\Models\StudentEnrollment::STATUS_TRANSFERRED;
        $enrollment->final_result_status = \App\Models\StudentEnrollment::FINAL_TRANSFERRED;
        $this->assertSame('Transferido', $enrollment->finalResultLabel());
    }

    public function test_it_keeps_ungraded_components_visible_without_calling_them_zero(): void
    {
        $course = new AcademicCourse(['class_hour_minutes' => 50]);
        $component = new CurriculumComponent(['workload_hours' => 80]);
        $component->setRelation('course', $course);
        $summary = (new StudentProgressSummary)->fromReport([
            'passingPoints' => 60,
            'minimumAttendance' => 75,
            'annualAttendance' => ['percentage' => null],
            'annualComponents' => collect([[
                'component' => $component, 'points' => 0,
                'complete_periods' => 0, 'total_periods' => 4,
                'periods' => collect([['average' => ['value' => null]]]),
                'attendance' => ['absent' => 0, 'justified' => 0, 'percentage' => null],
            ]]),
        ]);

        $this->assertNull($summary['components'][0]['points']);
        $this->assertSame(60.0, $summary['components'][0]['missing_points']);
    }
}
