<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistoryYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Substituição pontual de uma média técnica explicitamente aprovada. */
final class TechnicalPdfGradeCorrection
{
    public function correct(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $row = DiaryAssessmentResult::with(['assessment.component.course.academicYear', 'assessment.period', 'enrollment.student', 'enrollment.courses'])
            ->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($request['result_id'] ?? 0);
        $assessment = $row->assessment;
        $component = $assessment->component;
        $course = $component->course;
        $enrollment = $row->enrollment;
        $this->check(($request['replacement_approved'] ?? false) === true
            && ($request['source_grade_kind'] ?? '') === 'reported_average'
            && preg_match('/^[a-f0-9]{64}$/', $request['source_pdf_sha256'] ?? '')
            && $course->stage === AcademicCourse::STAGE_TECHNICAL
            && $course->id === ($request['course_id'] ?? null) && $component->id === ($request['component_id'] ?? null)
            && $assessment->school_class_id === ($request['class_id'] ?? null)
            && $assessment->academic_period_id === ($request['period_id'] ?? null)
            && $enrollment->id === ($request['enrollment_id'] ?? null)
            && $enrollment->person_id === ($request['person_id'] ?? null)
            && $enrollment->school_class_id === $assessment->school_class_id && $enrollment->courses->contains('id', $course->id)
            && $course->academicYear->reference_year === ($request['reference_year'] ?? null)
            && $component->isActiveInPeriod($assessment->period)
            && ! $assessment->is_recovery && (float) $assessment->maximum_score === 10.0, 'A correção não corresponde à nota técnica aprovada.');
        $regular = DiaryAssessment::where('school_class_id', $assessment->school_class_id)
            ->where('curriculum_component_id', $component->id)->where('academic_period_id', $assessment->academic_period_id)->get();
        $this->check($regular->count() === 1, 'A média técnica tem mais de uma avaliação e precisa de conferência específica.');
        $score = $request['score'] ?? null;
        $oldScore = $request['expected_score'] ?? null;
        $this->check(is_numeric($score) && $score >= 0 && $score <= 10 && is_numeric($oldScore)
            && in_array((float) $row->score, [(float) $oldScore, (float) $score], true), 'A nota atual diverge da substituição aprovada.');
        $historyYears = StudentAcademicHistoryYear::where('source', 'system')
            ->whereHas('enrollment', fn ($q) => $q->where('person_id', $enrollment->person_id)
                ->whereHas('schoolClass.academicYear', fn ($year) => $year->where('reference_year', $course->academicYear->reference_year)))
            ->with(['records' => fn ($q) => $q->where('curriculum_component_id', $component->id)])->orderBy('id')->get();
        $historyRecords = $historyYears->flatMap->records->sortBy('id')->values();
        $label = number_format(app(DiaryGradeCalculator::class)->roundToNearestHalf((float) $score), 1, ',', '');
        $before = ['result' => $row->getAttributes(), 'course' => $course->getAttributes(), 'component' => $component->getAttributes(),
            'period' => $assessment->period->getAttributes(), 'enrollment' => $enrollment->getAttributes(),
            'history_records' => $historyRecords->map->getAttributes()->all(),
            'protected_sha256' => $this->protectedDigest($assessment->school_class_id, $row->id)];
        $hash = hash('sha256', json_encode([$request, $before], JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash,
            'result_id' => $row->id, 'course_id' => $course->id, 'enrollment_id' => $enrollment->id,
            'score_before' => (float) $row->score, 'score_after' => (float) $score,
            'grades_to_update' => (float) $row->score === (float) $score ? 0 : 1,
            'history_scores_to_update' => $historyRecords->where('score_label', '!=', $label)->count(),
            'protected_sha256' => $before['protected_sha256']];
        if (! $apply) {
            return $result;
        }
        $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'A nota ou seus vínculos mudaram após a conferência.');
        if (! $result['grades_to_update'] && ! $result['history_scores_to_update']) {
            return $result;
        }
        $backup = 'diary-reconciliation/technical-pdf-grade-'.Str::uuid().'.json';
        $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before], JSON_THROW_ON_ERROR)), 'Falha ao guardar a nota anterior.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($row, $score, $historyRecords, $label): void {
            if ((float) $row->score !== (float) $score) {
                $row->update(['score' => $score]);
            }
            foreach ($historyRecords as $record) {
                if ($record->score_label !== $label) {
                    $record->update(['score_label' => $label]);
                }
            }
        });
        $this->check(hash_equals($before['protected_sha256'], $this->protectedDigest($assessment->school_class_id, $row->id)),
            'A correção alteraria outro registro técnico.');
        $attributes = $row->fresh()->getAttributes();
        $previous = $before['result'];
        unset($attributes['score'], $attributes['updated_at'], $previous['score'], $previous['updated_at']);
        $this->check($attributes === $previous, 'Outro campo da nota foi alterado.');
        AuditLog::create(['school_id' => $course->academicYear->school_id, 'auditable_type' => DiaryAssessmentResult::class,
            'auditable_id' => $row->id, 'action' => 'updated', 'old_values' => ['score' => $oldScore], 'new_values' => ['score' => $score],
            'metadata' => ['operation' => 'technical-pdf-grade-correction', 'backup_path' => $backup,
                'source_pdf_sha256' => $request['source_pdf_sha256'], 'snapshot_sha256' => $hash]]);

        return $result + ['backup_path' => $backup];
    }

    private function protectedDigest(int $classId, int $resultId): string
    {
        $class = SchoolClass::with(['academicYear.periods', 'courses.components', 'enrollments'])->findOrFail($classId);
        $assessments = DB::table('diary_assessments')->where('school_class_id', $classId)->orderBy('id')->get();
        $calls = DB::table('diary_attendance_records')->where('school_class_id', $classId)->orderBy('id')->get();

        return hash('sha256', json_encode([$class->toArray(), $assessments,
            DB::table('diary_assessment_results')->whereIn('diary_assessment_id', $assessments->pluck('id'))->where('id', '!=', $resultId)->orderBy('id')->get(),
            $calls, DB::table('diary_attendance_entries')->whereIn('diary_attendance_record_id', $calls->pluck('id'))->orderBy('id')->get(),
            DB::table('diary_contents')->where('school_class_id', $classId)->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
