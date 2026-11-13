<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\SchoolClassComponent;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Preenchimento pontual e conferido, exclusivamente em matrizes técnicas. */
final class TechnicalGradeCompleter
{
    public function complete(array $courseIds, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        $this->check(count($courseIds) >= 1 && count($courseIds) <= 20
            && count(array_unique($courseIds)) === count($courseIds)
            && collect($courseIds)->every(fn ($id) => is_int($id) && $id > 0), 'Informe apenas os identificadores das matrizes técnicas conferidas.');
        sort($courseIds);

        return DB::transaction(function () use ($courseIds, $apply, $expectedSnapshot): array {
            $courses = AcademicCourse::whereIn('id', $courseIds)->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())
                ->with(['academicYear.periods.assessmentRules', 'startsPeriod', 'endsPeriod', 'components.startsPeriod', 'components.endsPeriod',
                    'classes.startsPeriod', 'classes.endsPeriod', 'classes.enrollments.courses'])->get();
            $this->check($courses->count() === count($courseIds)
                && $courses->every(fn ($course) => $course->stage === AcademicCourse::STAGE_TECHNICAL), 'O lote contém matriz que não é de curso técnico.');
            $classIds = $courses->flatMap->classes->pluck('id')->unique()->sort()->values();
            $componentIds = $courses->flatMap->components->pluck('id')->unique();
            $assessments = DiaryAssessment::whereIn('school_class_id', $classIds)->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())
                ->with(['results' => fn ($q) => $q->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())])->get();
            $enrollments = $courses->flatMap->classes->flatMap->enrollments->unique('id')->keyBy('id');
            $origins = StudentPeriodConvalidation::whereIn('student_enrollment_id', $enrollments->keys())
                ->whereIn('curriculum_component_id', $componentIds)->orderBy('id')->get();
            $protected = $assessments->filter(fn ($assessment) => $assessment->is_recovery || ! $componentIds->contains($assessment->curriculum_component_id))->values()->toArray();
            $existingScores = $assessments->flatMap->results->whereNotNull('score')->sortBy('id')->map->getAttributes()->values()->all();
            $snapshot = [$courses->toArray(), $assessments->toArray(), $origins->toArray()];
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $basis = $present = [];
            foreach ($courses as $course) {
                $periodIds = $course->academicYear->periods->filter(fn ($period) => $period->starts_at->toDateString() <= now()->toDateString())->pluck('id');
                foreach ($assessments->whereIn('curriculum_component_id', $course->components->pluck('id'))->whereIn('academic_period_id', $periodIds) as $assessment) {
                    $this->check((float) $assessment->maximum_score > 0, 'Existe avaliação com escala inválida.');
                    foreach ($assessment->results->whereNotNull('score') as $result) {
                        $enrollment = $enrollments->get($result->student_enrollment_id);
                        if (! $enrollment) {
                            continue;
                        }
                        $score = (float) $result->score * 10 / (float) $assessment->maximum_score;
                        $this->check($score >= 0 && $score <= 10, 'Existe nota fora da escala da avaliação.');
                        $key = $course->academic_year_id.':'.$enrollment->person_id.':'.$assessment->curriculum_component_id;
                        $basis[$key] = isset($basis[$key]) ? max($basis[$key], $score) : $score;
                        $present[$key.':'.$assessment->academic_period_id] = true;
                    }
                }
                foreach ($origins->whereIn('curriculum_component_id', $course->components->pluck('id'))->whereIn('academic_period_id', $periodIds)->whereNotNull('score') as $origin) {
                    $enrollment = $enrollments->get($origin->student_enrollment_id);
                    if (! $enrollment) {
                        continue;
                    }
                    $key = $course->academic_year_id.':'.$enrollment->person_id.':'.$origin->curriculum_component_id;
                    $score = (float) $origin->score;
                    $this->check($score >= 0 && $score <= 10, 'Existe nota de origem fora da escala.');
                    $basis[$key] = isset($basis[$key]) ? max($basis[$key], $score) : $score;
                    $present[$key.':'.$origin->academic_period_id] = true;
                }
            }
            $plans = $people = [];
            $stats = ['student_periods_to_fill' => 0, 'student_periods_from_maximum' => 0, 'student_periods_from_default' => 0,
                'existing_student_periods_preserved' => 0, 'results_to_create' => 0, 'empty_results_to_fill' => 0, 'assessments_to_create' => 0];
            $assessmentPlans = [];
            $byCourse = [];
            foreach ($courses as $course) {
                $courseCount = 0;
                foreach ($course->classes as $class) {
                    $this->check($class->academic_year_id === $course->academic_year_id, 'A turma e a matriz pertencem a anos letivos diferentes.');
                    $students = $class->enrollments->filter(fn ($enrollment) => $enrollment->courses->contains('id', $course->id));
                    foreach ($course->components as $component) {
                        foreach ($course->academicYear->periods as $period) {
                            if ($period->starts_at->toDateString() > now()->toDateString() || ! $component->isActiveInPeriod($period)
                                || ($course->startsPeriod && $period->position < $course->startsPeriod->position)
                                || ($course->endsPeriod && $period->position > $course->endsPeriod->position)
                                || ($class->startsPeriod && $period->position < $class->startsPeriod->position)
                                || ($class->endsPeriod && $period->position > $class->endsPeriod->position)) {
                                continue;
                            }
                            $regular = $assessments->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)
                                ->where('academic_period_id', $period->id)->where('is_recovery', false);
                            foreach ($students as $enrollment) {
                                $lastDate = min($period->ends_at->toDateString(), now()->toDateString(), $enrollment->attendanceEndsAt() ?? '9999-12-31');
                                if ($lastDate < max($period->starts_at->toDateString(), $enrollment->enrolled_at?->toDateString() ?? '0000-01-01')) {
                                    continue;
                                }
                                $key = $course->academic_year_id.':'.$enrollment->person_id.':'.$component->id;
                                $people[$enrollment->person_id] = true;
                                if (isset($present[$key.':'.$period->id])) {
                                    $stats['existing_student_periods_preserved']++;

                                    continue;
                                }
                                $score = $basis[$key] ?? 7.0;
                                $stats['student_periods_to_fill']++;
                                $stats[isset($basis[$key]) ? 'student_periods_from_maximum' : 'student_periods_from_default']++;
                                $courseCount++;
                                $rules = $period->assessmentRules->where('school_id', $course->academicYear->school_id)->sortBy('position');
                                $this->check($rules->isNotEmpty(), 'Período sem avaliações regulares configuradas.');
                                $slots = $regular->map(fn ($assessment) => ['assessment' => $assessment, 'rule' => null])->values();
                                foreach ($rules as $rule) {
                                    if (! $regular->contains('school_assessment_rule_id', $rule->id)) {
                                        $slots->push(['assessment' => null, 'rule' => $rule]);
                                    }
                                }
                                foreach ($slots as $slot) {
                                    $assessment = $slot['assessment'];
                                    $rule = $slot['rule'];
                                    $maximum = (float) ($assessment?->maximum_score ?? $rule->maximum_score);
                                    $this->check($maximum > 0 && (int) ($assessment?->weight ?? $rule->weight) > 0, 'Escala ou peso inválido para o preenchimento.');
                                    $assessmentKey = $assessment ? 'existing:'.$assessment->id : implode(':', [$class->id, $component->id, $period->id, $rule->id]);
                                    if (! $assessment && ! isset($assessmentPlans[$assessmentKey])) {
                                        $stats['assessments_to_create']++;
                                    }
                                    $assessmentPlans[$assessmentKey] = compact('assessment', 'rule', 'class', 'component', 'period');
                                    $result = $assessment?->results->firstWhere('student_enrollment_id', $enrollment->id);
                                    $this->check($result?->score === null, 'Uma nota existente seria substituída.');
                                    $stats[$result ? 'empty_results_to_fill' : 'results_to_create']++;
                                    $plans[] = ['assessment_key' => $assessmentKey, 'enrollment_id' => $enrollment->id,
                                        'score' => round($score * $maximum / 10, 2), 'result' => $result];
                                }
                            }
                        }
                    }
                }
                $byCourse[] = ['course_id' => $course->id, 'academic_year_id' => $course->academic_year_id, 'student_periods_to_fill' => $courseCount];
            }
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'course_ids' => $courseIds,
                'class_ids' => $classIds->all(), 'students_count' => count($people), 'stats' => $stats, 'courses' => $byCourse];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot), 'As notas ou os vínculos mudaram após a conferência. Execute nova simulação.');
            $backup = 'diary-reconciliation/technical-grades-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode(['before' => $snapshot, 'plan' => $result], JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            foreach ($assessmentPlans as &$plan) {
                if ($plan['assessment']) {
                    continue;
                }
                $assignment = SchoolClassComponent::where('school_class_id', $plan['class']->id)->where('curriculum_component_id', $plan['component']->id)->first();
                $plan['assessment'] = DiaryAssessment::create([
                    'school_class_id' => $plan['class']->id, 'curriculum_component_id' => $plan['component']->id,
                    'academic_period_id' => $plan['period']->id, 'school_assessment_rule_id' => $plan['rule']->id,
                    'is_recovery' => false, 'teacher_person_id' => $assignment?->teacher_person_id,
                    'title' => $plan['rule']->label(), 'weight' => $plan['rule']->weight, 'maximum_score' => $plan['rule']->maximum_score,
                    'assessment_date' => min($plan['period']->ends_at->toDateString(), now()->toDateString()),
                ]);
            }
            unset($plan);
            foreach ($plans as $plan) {
                if ($plan['result']) {
                    $plan['result']->fill(['score' => $plan['score']])->save();
                } else {
                    DiaryAssessmentResult::create(['diary_assessment_id' => $assessmentPlans[$plan['assessment_key']]['assessment']->id,
                        'student_enrollment_id' => $plan['enrollment_id'], 'score' => $plan['score']]);
                }
            }
            $afterProtected = DiaryAssessment::whereIn('school_class_id', $classIds)->where(function ($q) use ($componentIds) {
                $q->where('is_recovery', true)->orWhereNotIn('curriculum_component_id', $componentIds);
            })->with(['results' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()->toArray();
            $this->check($protected === $afterProtected, 'Uma recuperação ou componente fora do lote foi alterado.');
            $this->check($existingScores === DiaryAssessmentResult::whereIn('id', array_column($existingScores, 'id'))
                ->orderBy('id')->get()->map->getAttributes()->all(), 'Uma nota já lançada foi alterada.');
            foreach ($courses as $course) {
                AuditLog::create(['school_id' => $course->academicYear->school_id, 'auditable_type' => AcademicCourse::class,
                    'auditable_id' => $course->id, 'action' => 'updated', 'metadata' => ['operation' => 'technical-grade-completion',
                        'backup_path' => $backup, 'snapshot_sha256' => $hash, 'stats' => $stats]]);
            }

            return $result + ['backup_path' => $backup];
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
