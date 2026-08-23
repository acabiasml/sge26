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

    public function synchronize(Person $student, int $schoolId, int $personId, string $stage): StudentAcademicHistory
    {
        return DB::transaction(function () use ($student, $schoolId, $personId, $stage): StudentAcademicHistory {
            $stageLabel = AcademicCourse::STAGE_LABELS[$stage];
            $history = StudentAcademicHistory::query()->firstOrCreate(
                ['person_id' => $student->id, 'is_unified' => true, 'education_stage' => $stage],
                [
                    'school_id' => $schoolId,
                    'created_by_person_id' => $personId,
                    'updated_by_person_id' => $personId,
                    'title' => 'Histórico escolar - '.$stageLabel,
                    'stage' => $stageLabel,
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

            $enrollments = $enrollments->filter(
                fn (StudentEnrollment $enrollment): bool => $enrollment->courses->contains('stage', $stage)
            )->values();
            $enrollmentIds = $enrollments->pluck('id');
            $obsoleteYears = $history->years()->where('source', 'system');
            if ($enrollmentIds->isNotEmpty()) {
                $obsoleteYears->whereNotIn('student_enrollment_id', $enrollmentIds);
            }
            $obsoleteYears->delete();
            foreach ($enrollments as $position => $enrollment) {
                $this->synchronizeEnrollment($history, $enrollment, $position + 1, $stage);
            }
            $history->update(['updated_by_person_id' => $personId]);

            return $history->fresh(['years.records', 'components.records.year']);
        });
    }

    private function synchronizeEnrollment(StudentAcademicHistory $history, StudentEnrollment $enrollment, int $position, string $stage): void
    {
        $report = $this->reportCardBuilder->build($enrollment);
        $course = $report['courses']->firstWhere('stage', $stage);
        $stageComponents = $report['annualComponents']->filter(fn (array $item): bool => $item['component']->course?->stage === $stage);
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
                'workload_hours' => $stageComponents->sum(fn (array $item) => $item['component']->calculatedWorkloadHours()),
                'school_days' => $year->schoolDayCount(),
                'attendance_label' => $percentage !== null ? number_format((float) $percentage, 2, ',', '.').'%' : null,
                'minimum_attendance_percentage' => $year->minimum_attendance_percentage,
            ],
        );

        foreach ($stageComponents as $componentReport) {
            $component = $componentReport['component'];
            $formation = CurriculumCatalog::formationLabelForArea($component->course, $component->area);
            if ($stage === AcademicCourse::STAGE_ELEMENTARY && $formation === CurriculumCatalog::FORMATION_COMPLEMENTARY) {
                $formation = 'Parte Diversificada';
            }
            $historyComponent = $history->components()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($component->name), 'UTF-8')])
                ->where('knowledge_area', $component->area?->name)
                ->first();
            $historyComponent ??= $history->components()->create([
                    'position' => $history->components()->count() + 1,
                    'formation' => $formation,
                    'knowledge_area' => $component->area?->name,
                    'name' => $component->name,
                ]);
            $historyComponent->update(['formation' => $formation]);
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
