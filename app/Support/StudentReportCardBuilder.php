<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\SchoolClassComponent;
use App\Models\StudentBehaviorGrade;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Support\Collection;

class StudentReportCardBuilder
{
    public function __construct(
        private readonly DiaryGradeCalculator $gradeCalculator,
        private readonly AttendanceSummaryCalculator $attendanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(StudentEnrollment $enrollment): array
    {
        $enrollment->load([
            'student',
            'finalResultCalculatedBy',
            'schoolClass.academicYear.school.concepts',
            'schoolClass.academicYear.school.academicCriteria',
            'periodConvalidations.period',
            'periodConvalidations.component',
            'courses.components.area',
            'courses.components.course',
        ]);

        $schoolClass = $enrollment->schoolClass;
        $academicYear = $schoolClass->academicYear;
        $periods = $academicYear->periods()->orderBy('position')->get();
        $courses = $enrollment->courses->sortBy('name')->values();
        $components = $courses
            ->flatMap(fn ($course) => $course->components->where('active', true))
            ->unique('id')
            ->sortBy([
                [fn (CurriculumComponent $component): string => $component->area?->name ?? 'Área não definida', 'asc'],
                [fn (CurriculumComponent $component): string => $component->name, 'asc'],
            ])
            ->values();
        $componentIds = $components->pluck('id');
        $periodIds = $periods->pluck('id');

        $assignments = SchoolClassComponent::query()
            ->with('teacher')
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('curriculum_component_id', $componentIds)
            ->get()
            ->keyBy('curriculum_component_id');

        $assessments = DiaryAssessment::query()
            ->with(['results' => fn ($query) => $query->where('student_enrollment_id', $enrollment->id), 'rule'])
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('curriculum_component_id', $componentIds)
            ->whereIn('academic_period_id', $periodIds)
            ->orderBy('is_recovery')
            ->orderBy('school_assessment_rule_id')
            ->orderBy('title')
            ->get();

        $attendance = DiaryAttendanceRecord::query()
            ->with(['entries' => fn ($query) => $query->where('student_enrollment_id', $enrollment->id)])
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('curriculum_component_id', $componentIds)
            ->whereIn('academic_period_id', $periodIds)
            ->whereHas('entries', fn ($query) => $query->where('student_enrollment_id', $enrollment->id))
            ->orderBy('class_date')
            ->get();

        $justifications = DiaryAttendanceJustification::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->get();

        $behaviorGrades = StudentBehaviorGrade::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereIn('academic_period_id', $periodIds)
            ->get()
            ->keyBy('academic_period_id');

        $convalidations = $enrollment->periodConvalidations
            ->whereIn('academic_period_id', $periodIds)
            ->whereIn('curriculum_component_id', $componentIds)
            ->keyBy(fn (StudentPeriodConvalidation $convalidation): string => $convalidation->academic_period_id.'-'.$convalidation->curriculum_component_id);

        $periodReports = $periods->map(function (AcademicPeriod $period) use ($components, $assessments, $attendance, $justifications, $enrollment, $behaviorGrades, $assignments, $convalidations): array {
            $componentReports = $components
                ->filter(fn (CurriculumComponent $component): bool => $component->isActiveInPeriod($period))
                ->map(function (CurriculumComponent $component) use ($period, $assessments, $attendance, $justifications, $enrollment, $assignments, $convalidations): array {
                    $componentAssessments = $assessments
                        ->where('academic_period_id', $period->id)
                        ->where('curriculum_component_id', $component->id)
                        ->values();
                    $average = $this->gradeCalculator->averages(collect([$enrollment]), $componentAssessments)[$enrollment->id];
                    $convalidation = $convalidations->get($period->id.'-'.$component->id);

                    if ($convalidation && ! ($average['complete'] ?? false)) {
                        $average = [
                            'value' => (float) $convalidation->score,
                            'completed_assessments' => 1,
                            'total_assessments' => 1,
                            'complete' => true,
                            'source' => 'convalidated',
                        ];
                    }

                    $componentAttendance = $attendance
                        ->where('academic_period_id', $period->id)
                        ->where('curriculum_component_id', $component->id)
                        ->values();

                    return [
                        'period' => $period,
                        'component' => $component,
                        'assignment' => $assignments->get($component->id),
                        'assessments' => $componentAssessments,
                        'average' => $average,
                        'convalidation' => $convalidation,
                        'attendance' => $this->attendanceCalculator->summarize($componentAttendance, $justifications),
                    ];
                })
                ->values();

            return [
                'period' => $period,
                'behavior' => $behaviorGrades->get($period->id),
                'components' => $componentReports,
            ];
        })->values();

        $annualComponents = $this->annualComponentSummary($components, $periodReports);
        $annualAttendance = $this->attendanceCalculator->aggregate($annualComponents->pluck('attendance'));

        return [
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'schoolClass' => $schoolClass,
            'academicYear' => $academicYear,
            'courses' => $courses,
            'periods' => $periods,
            'periodReports' => $periodReports,
            'annualComponents' => $annualComponents,
            'annualAttendance' => $annualAttendance,
            'passingPoints' => (float) $academicYear->passing_points,
            'minimumAttendance' => (int) $academicYear->minimum_attendance_percentage,
            'finalResult' => [
                'status' => $enrollment->final_result_status,
                'label' => $enrollment->finalResultLabel(),
                'details' => $enrollment->final_result_details ?? [],
                'calculated_at' => $enrollment->final_result_calculated_at,
                'calculated_by' => $enrollment->finalResultCalculatedBy,
            ],
        ];
    }

    /**
     * @param  Collection<int, CurriculumComponent>  $components
     * @param  Collection<int, array<string, mixed>>  $periodReports
     * @return Collection<int, array<string, mixed>>
     */
    private function annualComponentSummary(Collection $components, Collection $periodReports): Collection
    {
        return $components->map(function (CurriculumComponent $component) use ($periodReports): array {
            $periodComponentReports = $periodReports
                ->map(fn (array $periodReport) => $periodReport['components']->first(fn (array $componentReport): bool => $componentReport['component']->id === $component->id))
                ->filter()
                ->values();
            $points = $periodComponentReports->sum(fn (array $componentReport): float => (float) ($componentReport['average']['value'] ?? 0));
            $attendance = $this->attendanceCalculator->aggregate($periodComponentReports->pluck('attendance'));

            return [
                'component' => $component,
                'periods' => $periodComponentReports,
                'points' => $points,
                'complete_periods' => $periodComponentReports->filter(fn (array $componentReport): bool => (bool) ($componentReport['average']['complete'] ?? false))->count(),
                'total_periods' => $periodComponentReports->count(),
                'attendance' => $attendance,
            ];
        })->values();
    }
}
