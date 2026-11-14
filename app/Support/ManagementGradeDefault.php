<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Decisão expressa da gestão: zero/ausência de nota recebe seis na escala 0–10. */
final class ManagementGradeDefault
{
    public const SOURCE = 'management_directed_default_grade';

    public function complete(int $schoolId, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        $school = School::findOrFail($schoolId);
        $this->check($school->id === 1 && $school->legacy_source === 'beaba', 'Operação limitada ao Liceu.');

        return DB::transaction(function () use ($school, $apply, $expectedSnapshot): array {
            $courses = AcademicCourse::whereHas('academicYear', fn ($q) => $q->where('school_id', $school->id)->whereBetween('reference_year', [2022, 2025]))
                ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')
                ->with(['academicYear.periods.assessmentRules', 'startsPeriod', 'endsPeriod', 'components.startsPeriod', 'components.endsPeriod',
                    'classes.startsPeriod', 'classes.endsPeriod', 'classes.enrollments.courses'])->get();
            $classIds = $courses->flatMap->classes->pluck('id')->unique()->sort()->values();
            $this->check($courses->count() > 0 && $classIds->count() <= 60, 'Recorte sem turmas ou acima do limite administrativo.');
            $assessments = DiaryAssessment::whereIn('school_class_id', $classIds)->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())
                ->with(['results' => fn ($q) => $q->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())])->get();
            $enrollments = $courses->flatMap->classes->flatMap->enrollments->unique('id')->keyBy('id');
            $credits = StudentPeriodConvalidation::whereIn('student_enrollment_id', $enrollments->keys())->orderBy('id')
                ->when($apply, fn ($q) => $q->lockForUpdate())->get();
            $before = [$courses->toArray(), $assessments->toArray(), $credits->toArray()];
            $hash = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
            $assessmentPlans = $resultPlans = $creditPlans = $details = [];
            $stats = ['eligible_student_component_periods' => 0, 'zero_results_to_replace' => 0, 'null_results_to_fill' => 0,
                'missing_results_to_create' => 0, 'assessments_to_create' => 0, 'origin_grades_to_fill' => 0,
                'positive_results_preserved' => 0, 'positive_origin_grades_preserved' => 0, 'outside_enrollment_periods_skipped' => 0,
                'optional_recoveries_not_created' => 0];
            $lastHeartbeat = microtime(true);
            foreach ($courses as $course) {
                foreach ($course->classes as $class) {
                    $this->check($class->academic_year_id === $course->academic_year_id, 'Turma e matriz de anos diferentes.');
                    foreach ($course->components as $component) {
                        if (! $component->active || Str::lower(Str::ascii(trim($component->name))) === 'comportamento') {
                            continue;
                        }
                        foreach ($course->academicYear->periods as $period) {
                            if (microtime(true) - $lastHeartbeat >= 3) {
                                DB::select('select 1');
                                $lastHeartbeat = microtime(true);
                            }
                            if (! $period->starts_at || ! $period->ends_at
                                || $period->starts_at->toDateString() < '2022-01-01' || $period->ends_at->toDateString() > '2025-12-31'
                                || ! $component->isActiveInPeriod($period)
                                || ($course->startsPeriod && $period->position < $course->startsPeriod->position)
                                || ($course->endsPeriod && $period->position > $course->endsPeriod->position)
                                || ($class->startsPeriod && $period->position < $class->startsPeriod->position)
                                || ($class->endsPeriod && $period->position > $class->endsPeriod->position)) {
                                continue;
                            }
                            $existing = $assessments->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id);
                            $regular = $existing->where('is_recovery', false);
                            // Diários legados com uma nota bimestral não ganham avaliações extras.
                            $slots = $regular->map(fn ($a) => ['assessment' => $a, 'rule' => null])->values();
                            if ($regular->isEmpty()) {
                                $rules = $period->assessmentRules->where('school_id', $school->id)->sortBy('position');
                                $slots = $rules->isNotEmpty() ? $rules->map(fn ($r) => ['assessment' => null, 'rule' => $r])->values()
                                    : collect([['assessment' => null, 'rule' => null]]);
                            }
                            foreach ($class->enrollments->filter(fn ($e) => $e->courses->contains('id', $course->id)) as $enrollment) {
                                $end = $enrollment->attendanceEndsAt();
                                $this->check($enrollment->enrolled_at !== null
                                    && (! in_array($enrollment->status, ['transferido', 'cancelado', 'reclassificado', 'nao_renovado'], true) || $end !== null),
                                    'Matrícula sem datas suficientes para determinar sua vigência.');
                                if ($enrollment->enrolled_at->toDateString() > $period->ends_at->toDateString()
                                    || ($end && $end < $period->starts_at->toDateString())) {
                                    $stats['outside_enrollment_periods_skipped']++;

                                    continue;
                                }
                                $stats['eligible_student_component_periods']++;
                                $context = ['year' => $course->academicYear->reference_year, 'class_id' => $class->id, 'class' => $class->name,
                                    'component_id' => $component->id, 'component' => $component->name, 'period_id' => $period->id, 'period' => $period->name,
                                    'enrollment_id' => $enrollment->id, 'person_id' => $enrollment->person_id];
                                $origin = $credits->first(fn ($c) => $c->student_enrollment_id === $enrollment->id
                                    && $c->academic_period_id === $period->id && $c->curriculum_component_id === $component->id);
                                if ($origin) {
                                    if ($origin->score === null || (float) $origin->score === 0.0) {
                                        $creditPlans[$origin->id] = $origin;
                                        $stats['origin_grades_to_fill']++;
                                        $details[] = $context + ['kind' => 'origin', 'old_score' => $origin->score, 'new_score' => 6];
                                    } else {
                                        $stats['positive_origin_grades_preserved']++;
                                    }
                                }
                                $hasOriginGrade = $origin && ($origin->score !== null || isset($creditPlans[$origin->id]));
                                foreach ($slots as $slot) {
                                    $assessment = $slot['assessment'];
                                    $rule = $slot['rule'];
                                    $result = $assessment?->results->firstWhere('student_enrollment_id', $enrollment->id);
                                    if ($result?->score !== null && (float) $result->score !== 0.0) {
                                        $stats['positive_results_preserved']++;

                                        continue;
                                    }
                                    // Uma nota de origem válida já cobre o período sem inventar avaliações.
                                    if ($hasOriginGrade && $result?->score === null) {
                                        continue;
                                    }
                                    $maximum = (float) ($assessment?->maximum_score ?? $rule?->maximum_score ?? 10);
                                    $this->check($maximum > 0 && (int) ($assessment?->weight ?? $rule?->weight ?? 10) > 0, 'Escala ou peso inválido.');
                                    $key = $assessment ? 'existing:'.$assessment->id : implode(':', [$class->id, $component->id, $period->id, $rule?->id ?? 'administrative']);
                                    if (! isset($assessmentPlans[$key])) {
                                        $assessmentPlans[$key] = compact('assessment', 'rule', 'class', 'component', 'period');
                                        $stats['assessments_to_create'] += $assessment ? 0 : 1;
                                    }
                                    $kind = $result ? ($result->score === null ? 'null' : 'zero') : 'missing';
                                    $stats[match ($kind) {
                                        'null' => 'null_results_to_fill', 'zero' => 'zero_results_to_replace', default => 'missing_results_to_create'
                                    }]++;
                                    $score = round($maximum * .6, 2);
                                    $resultPlans[] = ['assessment_key' => $key, 'enrollment_id' => $enrollment->id, 'score' => $score, 'result' => $result];
                                    $details[] = $context + ['kind' => $kind, 'assessment_id' => $assessment?->id,
                                        'old_score' => $result?->score, 'new_score' => $score, 'maximum_score' => $maximum];
                                }
                                foreach ($existing->where('is_recovery', true) as $recovery) {
                                    $result = $recovery->results->firstWhere('student_enrollment_id', $enrollment->id);
                                    if ($result?->score === null) {
                                        $stats['optional_recoveries_not_created']++;

                                        continue;
                                    }
                                    if ((float) $result->score !== 0.0) {
                                        $stats['positive_results_preserved']++;

                                        continue;
                                    }
                                    $maximum = (float) $recovery->maximum_score;
                                    $this->check($maximum > 0, 'Recuperação com escala inválida.');
                                    $key = 'existing:'.$recovery->id;
                                    $assessmentPlans[$key] = ['assessment' => $recovery];
                                    $resultPlans[] = ['assessment_key' => $key, 'enrollment_id' => $enrollment->id, 'score' => round($maximum * .6, 2), 'result' => $result];
                                    $stats['zero_results_to_replace']++;
                                    $details[] = $context + ['kind' => 'zero_recovery', 'assessment_id' => $recovery->id, 'old_score' => $result->score, 'new_score' => round($maximum * .6, 2)];
                                }
                            }
                        }
                    }
                }
            }
            $report = ['mode' => $apply ? 'apply' : 'dry-run', 'operation' => self::SOURCE, 'school_id' => $school->id,
                'years' => [2022, 2023, 2024, 2025], 'normalized_default_grade' => 6, 'snapshot_sha256' => $hash,
                'stats' => $stats, 'changes' => $details];
            if (! $apply) {
                return $report;
            }
            $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'Notas ou vínculos mudaram desde a simulação.');
            if (count($resultPlans) + count($creditPlans) === 0) {
                return $report + ['idempotent' => true];
            }
            $batch = (string) Str::uuid();
            $backup = 'diary-reconciliation/management-grades-'.$batch.'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode(['before' => $before, 'plan' => $report], JSON_THROW_ON_ERROR)), 'Falha na cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            $note = 'Nota 6,0 na escala 0–10 por decisão administrativa da gestão; substituição de zero ou preenchimento de nota ausente. Lote '.$batch.'.';
            Model::withoutEvents(function () use (&$assessmentPlans, $resultPlans, $creditPlans, $note, $batch): void {
                foreach ($assessmentPlans as &$plan) {
                    if ($plan['assessment']) {
                        continue;
                    }
                    $plan['assessment'] = DiaryAssessment::create(['school_class_id' => $plan['class']->id, 'curriculum_component_id' => $plan['component']->id,
                        'academic_period_id' => $plan['period']->id, 'school_assessment_rule_id' => $plan['rule']?->id,
                        'title' => $plan['rule']?->label() ?? 'Nota do período', 'maximum_score' => $plan['rule']?->maximum_score ?? 10,
                        'weight' => $plan['rule']?->weight ?? 10, 'is_recovery' => false, 'notes' => $note,
                        'legacy_source' => self::SOURCE, 'legacy_metadata' => ['provenance' => self::SOURCE, 'batch_id' => $batch, 'normalized_grade' => 6]]);
                }
                unset($plan);
                foreach ($resultPlans as $plan) {
                    $row = $plan['result'] ?? new DiaryAssessmentResult(['diary_assessment_id' => $assessmentPlans[$plan['assessment_key']]['assessment']->id,
                        'student_enrollment_id' => $plan['enrollment_id']]);
                    $row->fill(['score' => $plan['score'], 'updated_by_person_id' => null, 'notes' => trim(($row->notes ?? '').' '.$note)])->save();
                }
                foreach ($creditPlans as $row) {
                    $row->fill(['score' => 6, 'notes' => trim(($row->notes ?? '').' '.$note)])->save();
                }
            });
            // Atualiza o aproveitamento da origem sem criar notas fora da matrícula de destino.
            foreach (StudentEnrollment::whereIn('id', $enrollments->keys())->whereNotNull('reclassified_from_enrollment_id')->with('reclassifiedFrom')->get() as $destination) {
                app(ReclassificationAttendanceCredits::class)->synchronize($destination->reclassifiedFrom, $destination);
            }
            AuditLog::create(['school_id' => $school->id, 'auditable_type' => School::class, 'auditable_id' => $school->id, 'action' => 'updated',
                'metadata' => ['operation' => self::SOURCE, 'years' => [2022, 2023, 2024, 2025], 'batch_id' => $batch,
                    'normalized_grade' => 6, 'snapshot_sha256' => $hash, 'backup_path' => $backup, 'stats' => $stats]]);

            return $report + ['batch_id' => $batch, 'backup_path' => $backup];
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
