<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;

class UnifiedStudentHistorySynchronizer
{
    public function __construct(private readonly StudentReportCardBuilder $reportCardBuilder) {}

    public function synchronize(Person $student, int $schoolId, int $personId): StudentAcademicHistory
    {
        return DB::transaction(function () use ($student, $schoolId, $personId): StudentAcademicHistory {
            $history = StudentAcademicHistory::query()->firstOrCreate(
                ['person_id' => $student->id, 'is_unified' => true],
                [
                    'school_id' => $schoolId,
                    'created_by_person_id' => $personId,
                    'updated_by_person_id' => $personId,
                    'title' => 'Histórico escolar unificado',
                    'stage' => 'Ensino Fundamental e Ensino Médio',
                    'legal_basis' => 'Lei Federal nº 9.394/1996 (LDB) e normas educacionais aplicáveis.',
                    'issued_place' => 'Poxoréu-MT',
                    'issued_date' => now()->toDateString(),
                    'active' => true,
                ],
            );

            $enrollments = StudentEnrollment::query()
                ->where('person_id', $student->id)
                ->with(['schoolClass.academicYear.school', 'courses.components.area', 'courses.components.course'])
                ->get()
                ->sortBy(fn (StudentEnrollment $item) => $item->schoolClass?->academicYear?->reference_year ?? 0)
                ->values();

            foreach ($enrollments as $position => $enrollment) {
                $this->synchronizeEnrollment($history, $enrollment, $position + 1);
            }

            $history->update(['updated_by_person_id' => $personId]);

            return $history->fresh(['years.records', 'components.records.year']);
        });
    }

    private function synchronizeEnrollment(StudentAcademicHistory $history, StudentEnrollment $enrollment, int $position): void
    {
        $report = $this->reportCardBuilder->build($enrollment);
        $course = $report['courses']->first();
        $year = $report['academicYear'];
        $school = $year->school;
        $attendance = $report['annualAttendance'];
        $percentage = $attendance['percentage'] ?? null;
        $yearRow = $history->years()->updateOrCreate(
            ['student_enrollment_id' => $enrollment->id],
            [
                'position' => $position,
                'source' => 'system',
                'label' => $course?->name ?: $report['schoolClass']->name,
                'year' => (string) ($year->reference_year ?: $year->starts_at?->year),
                'stage' => AcademicCourse::STAGE_LABELS[$course?->stage] ?? 'Etapa não informada',
                'modality' => AcademicCourse::MODALITY_LABELS[$course?->modality] ?? 'Regular',
                'grade_phase' => $course?->name ?: $report['schoolClass']->name,
                'school_name' => $school->legal_name ?: $school->name,
                'school_authorization' => $school->letterhead_text,
                'city' => $school->city,
                'state' => $school->state,
                'country' => 'Brasil',
                'transcript_mode' => 'detailed',
                'final_result' => $report['finalResult']['label'],
                'workload_hours' => $report['annualComponents']->sum(fn (array $item) => $item['component']->calculatedWorkloadHours()),
                'school_days' => $year->schoolDayCount(),
                'attendance_label' => $percentage !== null ? number_format((float) $percentage, 2, ',', '.').'%' : null,
                'minimum_attendance_percentage' => $year->minimum_attendance_percentage,
            ],
        );

        foreach ($report['annualComponents'] as $componentReport) {
            $component = $componentReport['component'];
            $historyComponent = $history->components()->firstOrCreate(
                ['name' => $component->name],
                [
                    'position' => $history->components()->count() + 1,
                    'formation' => CurriculumCatalog::formationLabelForArea($component->course, $component->area),
                    'knowledge_area' => $component->area?->name,
                ],
            );
            $periodCount = max(1, (int) $componentReport['total_periods']);
            $score = $componentReport['complete_periods'] > 0 ? round((float) $componentReport['points'] / $periodCount, 2) : null;
            $componentAttendance = $componentReport['attendance'];
            $componentPercentage = $componentAttendance['percentage'] ?? null;

            $historyComponent->records()->updateOrCreate(
                ['student_academic_history_year_id' => $yearRow->id],
                [
                    'score_label' => $score !== null ? number_format($score, 2, ',', '.') : '-',
                    'score_numeric' => $score,
                    'workload_hours' => $component->calculatedWorkloadHours(),
                    'frequency_label' => $componentPercentage !== null ? number_format((float) $componentPercentage, 2, ',', '.').'%' : null,
                    'frequency_percentage' => $componentPercentage,
                    'absences' => $componentAttendance['absent'] ?? null,
                    'result' => $report['finalResult']['label'],
                ],
            );
        }
    }
}
