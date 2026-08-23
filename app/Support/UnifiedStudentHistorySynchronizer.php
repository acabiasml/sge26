<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\Person;
use App\Models\StudentAcademicHistory;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $allEnrollments = StudentEnrollment::query()
                ->where('person_id', $student->id)
                ->with([
                    'schoolClass.academicYear.school',
                    'courses.components.area',
                    'courses.components.course',
                    'courses.components.startsPeriod',
                    'courses.components.endsPeriod',
                ])
                ->get()
                ->sortBy(fn (StudentEnrollment $item) => $item->schoolClass?->academicYear?->reference_year ?? 0)
                ->values();

            $enrollments = $allEnrollments->filter(
                fn (StudentEnrollment $enrollment): bool => $enrollment->courses->contains('stage', $stage)
            )->values();
            $technicalSources = $stage === AcademicCourse::STAGE_HIGH_SCHOOL
                ? $this->technicalSources($allEnrollments)
                : collect();
            $enrollmentIds = $enrollments->pluck('id');
            $obsoleteYears = $history->years()->where('source', 'system');
            if ($enrollmentIds->isNotEmpty()) {
                $obsoleteYears->whereNotIn('student_enrollment_id', $enrollmentIds);
            }
            $obsoleteYears->delete();
            foreach ($enrollments as $position => $enrollment) {
                $this->synchronizeEnrollment(
                    $history,
                    $enrollment,
                    $position + 1,
                    $stage,
                    $position < $enrollments->count() - 1,
                    $technicalSources,
                );
            }
            $history->components()->whereDoesntHave('records')->delete();
            $history->update(['updated_by_person_id' => $personId]);

            return $history->fresh(['years.records', 'components.records.year']);
        });
    }

    /** @param Collection<int, array{enrollment: StudentEnrollment, course: AcademicCourse, report: array<string, mixed>}> $technicalSources */
    private function synchronizeEnrollment(StudentAcademicHistory $history, StudentEnrollment $enrollment, int $position, string $stage, bool $hasLaterEnrollment, Collection $technicalSources): void
    {
        $report = $this->reportCardBuilder->build($enrollment);
        $course = $report['courses']->firstWhere('stage', $stage);
        $stageComponents = $report['annualComponents']->filter(fn (array $item): bool => $item['component']->course?->stage === $stage);
        $year = $report['academicYear'];
        $referenceYear = (int) ($year->reference_year ?: $year->starts_at?->year);
        $supplementalComponents = $stage === AcademicCourse::STAGE_HIGH_SCHOOL
            ? $this->technicalComponentsForYear($technicalSources, $referenceYear)
            : collect();
        $historyComponents = $stageComponents->concat($supplementalComponents)->values();
        $school = $year->school;
        $attendance = $report['annualAttendance'];
        $percentage = $attendance['percentage'] ?? null;
        $withoutTranscription = in_array($enrollment->status, [StudentEnrollment::STATUS_RECLASSIFIED, StudentEnrollment::STATUS_TRANSFERRED], true);
        $finalResult = $this->historicalResult($enrollment, $hasLaterEnrollment);
        $isInProgress = $finalResult === 'Cursando';
        $yearRow = $history->years()->updateOrCreate(
            ['student_enrollment_id' => $enrollment->id],
            [
                'position' => $position,
                'source' => 'system',
                'label' => $course?->name ?: $report['schoolClass']->name,
                'year' => (string) $referenceYear,
                'stage' => AcademicCourse::STAGE_LABELS[$course?->stage] ?? 'Etapa não informada',
                'modality' => AcademicCourse::MODALITY_LABELS[$course?->modality] ?? 'Regular',
                'grade_phase' => $course?->name ?: $report['schoolClass']->name,
                'school_name' => $school->name,
                'school_authorization' => $school->letterhead_text,
                'city' => $school->city,
                'state' => $school->state,
                'country' => 'Brasil',
                'transcript_mode' => $withoutTranscription ? 'no_transcription' : 'detailed',
                'final_result' => $finalResult,
                'workload_hours' => $withoutTranscription ? 0 : $historyComponents->sum(fn (array $item) => $this->historicalWorkloadHours($item['component'])),
                'school_days' => $year->schoolDayCount(),
                'attendance_label' => ! $withoutTranscription && $percentage !== null ? number_format((float) $percentage, 2, ',', '.').'%' : null,
                'minimum_attendance_percentage' => $year->minimum_attendance_percentage,
                'notes' => $enrollment->status === StudentEnrollment::STATUS_TRANSFERRED && $enrollment->transferred_at
                    ? 'Transferido em '.$enrollment->transferred_at->format('d/m/Y').'.'
                    : null,
            ],
        );

        if ($withoutTranscription) {
            $yearRow->records()->delete();

            return;
        }

        $synchronizedComponentIds = collect();
        foreach ($historyComponents as $componentReport) {
            $component = $componentReport['component'];
            $formation = CurriculumCatalog::formationLabelForArea($component->course, $component->area);
            $knowledgeArea = CurriculumCatalog::areaLabelForComponent($component->course, $component->area);
            if ($stage === AcademicCourse::STAGE_ELEMENTARY && Str::lower(trim($component->name)) === 'ensino religioso') {
                $formation = CurriculumCatalog::FORMATION_FGB;
            }
            if ($stage === AcademicCourse::STAGE_ELEMENTARY && $formation === CurriculumCatalog::FORMATION_COMPLEMENTARY) {
                $formation = 'Parte Diversificada';
            }
            $historyComponent = $history->components()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($component->name), 'UTF-8')])
                ->where('knowledge_area', $knowledgeArea)
                ->first();
            $historyComponent ??= $history->components()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($component->name), 'UTF-8')])
                ->whereIn('knowledge_area', ['Itinerário Formativo', 'Educação Profissional e Tecnológica', 'Parte Complementar', 'Área não definida'])
                ->first();
            $historyComponent ??= $history->components()->create([
                    'position' => $history->components()->count() + 1,
                    'formation' => $formation,
                    'knowledge_area' => $knowledgeArea,
                    'name' => $component->name,
                ]);
            $historyComponent->update([
                'formation' => $formation,
                'knowledge_area' => $knowledgeArea,
            ]);
            $synchronizedComponentIds->push($historyComponent->id);
            $periodCount = max(1, (int) $componentReport['total_periods']);
            $rawScore = $componentReport['complete_periods'] > 0 ? (float) $componentReport['points'] / $periodCount : null;
            $usesLegacyScale = filled($component->legacy_source) || filled($component->legacy_metadata);
            $score = ! $isInProgress && $rawScore !== null ? round($rawScore, $usesLegacyScale ? 0 : 2, PHP_ROUND_HALF_UP) : null;
            $componentAttendance = $componentReport['attendance'];
            $componentPercentage = $componentAttendance['percentage'] ?? null;

            $historyComponent->records()->updateOrCreate(
                ['student_academic_history_year_id' => $yearRow->id],
                [
                    'score_label' => $score !== null ? number_format($score, $usesLegacyScale ? 1 : 2, ',', '.') : '-',
                    'score_numeric' => $score,
                    'workload_hours' => $this->historicalWorkloadHours($component),
                    'frequency_label' => $componentPercentage !== null ? number_format((float) $componentPercentage, 2, ',', '.').'%' : null,
                    'frequency_percentage' => $componentPercentage,
                    'absences' => $componentAttendance['absent'] ?? null,
                    'result' => $finalResult,
                ],
            );
        }

        $obsoleteRecords = $yearRow->records();
        if ($synchronizedComponentIds->isNotEmpty()) {
            $obsoleteRecords->whereNotIn('student_academic_history_component_id', $synchronizedComponentIds->unique());
        }
        $obsoleteRecords->delete();
    }

    /**
     * @param  Collection<int, StudentEnrollment>  $enrollments
     * @return Collection<int, array{enrollment: StudentEnrollment, course: AcademicCourse, report: array<string, mixed>}>
     */
    private function technicalSources(Collection $enrollments): Collection
    {
        return $enrollments->flatMap(function (StudentEnrollment $enrollment): Collection {
            $technicalCourses = $enrollment->courses
                ->where('stage', AcademicCourse::STAGE_TECHNICAL)
                ->values();

            if ($technicalCourses->isEmpty()) {
                return collect();
            }

            $report = $this->reportCardBuilder->build($enrollment);

            return $technicalCourses->map(fn (AcademicCourse $course): array => [
                'enrollment' => $enrollment,
                'course' => $course,
                'report' => $report,
            ]);
        })->values();
    }

    /**
     * A matrícula técnica pode usar um calendário plurianual diferente do calendário
     * da formação geral. Cada componente é lançado na série regular do ano em que o
     * módulo termina, evitando repetir a mesma carga horária em dois anos.
     *
     * @param  Collection<int, array{enrollment: StudentEnrollment, course: AcademicCourse, report: array<string, mixed>}>  $sources
     * @return Collection<int, array<string, mixed>>
     */
    private function technicalComponentsForYear(Collection $sources, int $referenceYear): Collection
    {
        return $sources
            ->filter(fn (array $source): bool => $this->academicYearIncludes($source['report']['academicYear'], $referenceYear))
            ->groupBy(fn (array $source): string => $this->technicalProgramKey($source['course']->name))
            ->flatMap(function (Collection $programSources) use ($referenceYear): Collection {
                $source = $programSources
                    ->sortByDesc(fn (array $item): string => sprintf(
                        '%010d-%010d',
                        $item['report']['academicYear']->starts_at?->diffInDays($item['report']['academicYear']->ends_at) ?? 0,
                        $item['enrollment']->id,
                    ))
                    ->first();

                if (! $source) {
                    return collect();
                }

                $courseId = $source['course']->id;

                return $source['report']['annualComponents']
                    ->filter(fn (array $item): bool => $item['component']->academic_course_id === $courseId)
                    ->filter(fn (array $item): bool => $this->componentCompletionYear($item['component'], $source['report']['academicYear']) === $referenceYear)
                    ->values();
            })
            ->values();
    }

    private function academicYearIncludes(AcademicYear $academicYear, int $year): bool
    {
        $startsIn = (int) ($academicYear->starts_at?->year ?? $academicYear->reference_year);
        $endsIn = (int) ($academicYear->ends_at?->year ?? $startsIn);

        return $year >= $startsIn && $year <= $endsIn;
    }

    private function componentCompletionYear(CurriculumComponent $component, AcademicYear $academicYear): int
    {
        $component->loadMissing('startsPeriod', 'endsPeriod');

        return (int) (
            $component->endsPeriod?->ends_at?->year
            ?? $component->startsPeriod?->ends_at?->year
            ?? $academicYear->ends_at?->year
            ?? $academicYear->reference_year
        );
    }

    private function technicalProgramKey(string $name): string
    {
        $normalized = Str::lower(Str::ascii(trim($name)));
        $normalized = (string) preg_replace('/^curso\s+/', '', $normalized);

        return (string) preg_replace('/\s+/', ' ', $normalized);
    }

    private function historicalResult(StudentEnrollment $enrollment, bool $hasLaterEnrollment): string
    {
        if ($enrollment->status === StudentEnrollment::STATUS_RECLASSIFIED) {
            return 'Reclassificado';
        }

        if ($enrollment->status === StudentEnrollment::STATUS_TRANSFERRED) {
            return 'Transferido';
        }

        if (filled($enrollment->final_result_status) && $enrollment->final_result_status !== StudentEnrollment::FINAL_PENDING) {
            return $enrollment->finalResultLabel();
        }

        return $hasLaterEnrollment ? 'Aprovado' : 'Cursando';
    }

    private function historicalWorkloadHours(CurriculumComponent $component): float
    {
        return $component->calculatedWorkloadHours();
    }
}
