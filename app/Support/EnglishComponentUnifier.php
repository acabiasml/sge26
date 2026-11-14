<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolClassComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Operação pontual: Inglês único, com autoridade do segundo cadastro no segundo semestre. */
final class EnglishComponentUnifier
{
    public function unify(int $keepId, int $removeId, array $periodLoads, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($keepId, $removeId, $periodLoads, $apply, $expectedSnapshot) {
            $keep = CurriculumComponent::with('course.academicYear.periods', 'course.components')->lockForUpdate()->findOrFail($keepId);
            $remove = CurriculumComponent::lockForUpdate()->find($removeId);
            $this->check($keepId !== $removeId && $keep->course->stage === 'medio'
                && (string) Str::of($keep->name)->ascii()->lower() === 'lingua inglesa', 'O destino precisa ser Língua Inglesa do Ensino Médio.');
            $periods = $keep->course->academicYear->periods->sortBy('position')->values();
            $this->check($periods->count() === 4 && collect(array_keys($periodLoads))->map(fn ($id) => (int) $id)->sort()->values()->all() === $periods->pluck('id')->sort()->values()->all(), 'Informe os quatro bimestres da matriz.');
            foreach ($periods as $index => $period) {
                $load = $periodLoads[$period->id];
                $this->check(($load['weekly_lessons'] ?? null) === ($index < 2 ? 2 : 1)
                    && ($load['workload_hours'] ?? null) === ($index < 2 ? 15 : null), 'A distribuição confirmada é 2/2/1/1, com quinze horas em cada bimestre inicial.');
            }
            if (! $remove) {
                $this->check($keep->period_loads == $periodLoads && $keep->starts_period_id === null && $keep->ends_period_id === null, 'O componente removido não existe e o destino não corresponde à unificação.');

                return ['mode' => $apply ? 'apply' : 'dry-run', 'pending' => 0, 'component_id' => $keepId, 'annual_hours' => $keep->course->calculatedWorkloadHours(), 'english_hours' => $keep->calculatedWorkloadHours(), 'weekly_lessons' => $keep->weeklyLessonsLabel()];
            }
            $this->check($remove->academic_course_id === $keep->academic_course_id && $remove->knowledge_area_id === $keep->knowledge_area_id
                && (string) Str::of($remove->name)->ascii()->lower() === 'lingua inglesa ii'
                && $keep->ends_period_id === $periods[1]->id && $remove->starts_period_id === $periods[2]->id
                && ! $keep->hasPeriodLoads() && ! $remove->hasPeriodLoads(), 'Matriz, área ou divisão dos semestres divergentes.');
            $assignments = SchoolClassComponent::whereIn('curriculum_component_id', [$keepId, $removeId])->orderBy('id')->lockForUpdate()->get();
            $this->check($assignments->count() === 2 && $assignments->pluck('school_class_id')->unique()->count() === 1
                && $assignments->pluck('teacher_person_id')->filter()->unique()->count() === 1
                && $assignments->every(fn ($a) => $a->teacher_person_id !== null), 'A operação exige uma única turma e a mesma docente.');
            $sourceAssignment = $assignments->firstWhere('curriculum_component_id', $removeId);
            $targetAssignment = $assignments->firstWhere('curriculum_component_id', $keepId);
            $classId = $targetAssignment->school_class_id;
            $ids = [$keepId, $removeId];
            // Estes vínculos exigem decisão própria; não são apagados ou fundidos implicitamente.
            foreach (['diary_period_confirmations', 'diary_alerts', 'student_period_convalidations', 'curriculum_component_substitutions'] as $table) {
                if (Schema::hasTable($table)) {
                    $this->check(! DB::table($table)->whereIn('curriculum_component_id', $ids)->exists(), 'Há vínculos adicionais em '.$table.'; revise antes de unificar.');
                }
            }
            foreach (['school_class_component_substitutions', 'diary_excluded_dates'] as $table) {
                $this->check(! DB::table($table)->whereIn('school_class_component_id', $assignments->modelKeys())->exists(), 'Há vínculos adicionais em '.$table.'; revise antes de unificar.');
            }
            $assessments = DiaryAssessment::whereIn('curriculum_component_id', $ids)->with(['results' => fn ($q) => $q->orderBy('id')])->orderBy('id')->lockForUpdate()->get();
            $attendance = DiaryAttendanceRecord::whereIn('curriculum_component_id', $ids)->with(['entries' => fn ($q) => $q->orderBy('id')])->orderBy('id')->lockForUpdate()->get();
            $contents = DiaryContent::whereIn('curriculum_component_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $this->check($assessments->concat($attendance)->concat($contents)->every(fn ($r) => $r->school_class_id === $classId), 'Há registros em outra turma.');
            foreach ([$attendance, $contents] as $records) {
                $this->check($records->groupBy(fn ($r) => $r->class_date->toDateString())->every(fn ($rows) => $rows->count() === 1), 'Há chamadas ou conteúdos conflitantes na mesma data.');
            }
            $secondSemesterIds = $periods->slice(2)->pluck('id');
            $obsolete = $assessments->filter(fn ($a) => $a->curriculum_component_id === $keepId && $secondSemesterIds->contains($a->academic_period_id));
            $authoritative = $assessments->reject(fn ($a) => $obsolete->contains('id', $a->id));
            $this->check($authoritative->where('curriculum_component_id', $removeId)->every(fn ($a) => $secondSemesterIds->contains($a->academic_period_id)), 'O segundo cadastro possui avaliações fora do segundo semestre.');
            foreach ($obsolete as $assessment) {
                $this->check($authoritative->contains(fn ($a) => $a->curriculum_component_id === $removeId
                    && $a->academic_period_id === $assessment->academic_period_id && $a->school_assessment_rule_id === $assessment->school_assessment_rule_id
                    && $a->is_recovery === $assessment->is_recovery), 'Uma avaliação antiga não tem correspondente no segundo cadastro.');
            }
            $records = StudentAcademicHistoryRecord::whereIn('curriculum_component_id', $ids)->with('year', 'component')->orderBy('id')->lockForUpdate()->get();
            $this->check($records->every(fn ($r) => $r->year->source === 'system' && $r->year->year === (string) $keep->course->academicYear->reference_year
                && $r->year->final_result === 'Cursando' && $r->score_numeric === null && $r->score_label === '-'
                && StudentEnrollment::whereKey($r->year->student_enrollment_id)->where('school_class_id', $classId)->exists()), 'Há histórico manual, concluído ou fora da turma que exige revisão própria.');
            $profile = $keep->replicate();
            $profile->fill(['period_loads' => $periodLoads, 'weekly_lessons' => null, 'workload_hours' => null, 'starts_period_id' => null, 'ends_period_id' => null]);
            $profile->unsetRelation('startsPeriod')->unsetRelation('endsPeriod');
            $oldHours = round($keep->calculatedWorkloadHours() + $remove->calculatedWorkloadHours(), 2);
            $this->check(abs($profile->calculatedWorkloadHours($keep->course) - $oldHours) < .01, 'A unificação alteraria a carga total de Inglês.');
            $annualHours = $keep->course->calculatedWorkloadHours();
            $snapshot = ['components' => [$keep->getAttributes(), $remove->getAttributes()], 'course' => $keep->course->toArray(),
                'assignments' => $assignments->toArray(), 'assessments' => $assessments->toArray(), 'attendance' => $attendance->toArray(), 'contents' => $contents->toArray(),
                'history_records' => $records->toArray(), 'history_year_records' => DB::table('student_academic_history_records')->whereIn('student_academic_history_year_id', $records->pluck('student_academic_history_year_id'))->orderBy('id')->get()->toArray(),
                'slots' => DB::table('school_class_schedule_slots')->whereIn('school_class_component_id', $assignments->modelKeys())->orderBy('id')->get()->toArray(),
                'enrollments' => StudentEnrollment::where('school_class_id', $classId)->orderBy('id')->get()->toArray(), 'period_loads' => $periodLoads];
            $protected = [$this->preserved($attendance, 'curriculum_component_id'), $this->preserved($contents, 'curriculum_component_id'), $this->preserved($authoritative, 'curriculum_component_id')];
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'pending' => 1, 'snapshot_sha256' => $hash, 'component_id' => $keepId, 'removed_component_id' => $removeId,
                'annual_hours' => $annualHours, 'english_hours' => $oldHours, 'weekly_lessons' => '2 / 2 / 1 / 1',
                'assessments_to_archive' => $obsolete->count(), 'grades_to_archive' => $obsolete->sum(fn ($a) => $a->results->whereNotNull('score')->count()),
                'grades_to_keep' => $authoritative->sum(fn ($a) => $a->results->whereNotNull('score')->count()),
                'attendance_records' => $attendance->count(), 'attendance_lessons' => $attendance->sum('lesson_count'), 'contents' => $contents->count(), 'history_records_before' => $records->count(), 'history_records_after' => $records->pluck('student_academic_history_year_id')->unique()->count()];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot), 'Os registros mudaram após a conferência; execute nova simulação.');
            $backup = 'diary-reconciliation/english-unification-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode($snapshot, JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            foreach ($obsolete as $assessment) {
                $assessment->delete();
            }
            foreach ($authoritative->where('curriculum_component_id', $removeId) as $assessment) {
                $assessment->update(['curriculum_component_id' => $keepId]);
            }
            foreach ($attendance->concat($contents)->where('curriculum_component_id', $removeId) as $row) {
                $row->update(['curriculum_component_id' => $keepId]);
            }
            DB::table('school_class_schedule_slots')->where('school_class_component_id', $sourceAssignment->id)->update(['school_class_component_id' => $targetAssignment->id]);
            $sourceAssignment->delete();
            $keep->update(['period_loads' => $periodLoads, 'weekly_lessons' => null, 'workload_hours' => null, 'starts_period_id' => null, 'ends_period_id' => null]);
            // Fusão somente dos registros de Inglês deste ano; os demais anos/componentes permanecem intactos.
            foreach ($records->groupBy('student_academic_history_year_id') as $group) {
                $record = $group->firstWhere('curriculum_component_id', $keepId) ?? $group->first();
                $historyComponent = $record->component;
                $this->check($historyComponent->name === $keep->name || ! $historyComponent->records()->whereNotIn('id', $records->modelKeys())->exists(), 'O nome no histórico é compartilhado com outros anos; revise a separação antes de unificar.');
                $historyComponent->adoptLabels(['name' => $keep->name]);
                $catalog = app(MatrixHistoryCatalog::class)->syncComponent($keep);
                $historyComponent->catalogEntries()->syncWithoutDetaching([$catalog->id]);
                foreach ($group as $duplicate) {
                    if ($duplicate->id !== $record->id) {
                        $duplicate->delete();
                    }
                }
                $record->update(['curriculum_component_id' => $keepId]);
            }
            $remove->delete();
            foreach ($records->pluck('component')->unique('id') as $component) {
                if (! $component->records()->exists()) {
                    $component->delete();
                }
            }
            foreach ($records->groupBy('student_academic_history_year_id') as $group) {
                $year = $group->first()->year;
                $enrollment = StudentEnrollment::findOrFail($year->student_enrollment_id);
                $report = app(StudentReportCardBuilder::class)->build($enrollment);
                $summary = $report['annualComponents']->first(fn ($item) => $item['component']->id === $keepId);
                $this->check($summary !== null, 'O componente unificado não apareceu no boletim.');
                $attendanceSummary = $summary['attendance'];
                $percentage = $attendanceSummary['percentage'] ?? null;
                $year->records()->where('curriculum_component_id', $keepId)->update(['workload_hours' => $oldHours,
                    'completed_workload_hours' => app(AttendanceSummaryCalculator::class)->completedWorkloadHours($attendanceSummary),
                    'frequency_label' => $percentage !== null ? number_format($percentage, 2, ',', '.').'%' : null,
                    'frequency_percentage' => $percentage, 'absences' => $attendanceSummary['absent'] ?? null]);
                $year->update(['workload_hours' => $year->records()->sum('workload_hours')]);
            }
            $this->check(abs($keep->course->fresh()->calculatedWorkloadHours() - $annualHours) < .01, 'A carga total da matriz mudou.');
            $this->check($this->preserved($attendance->fresh(), 'curriculum_component_id') === $protected[0]
                && $this->preserved($contents->fresh(), 'curriculum_component_id') === $protected[1]
                && $this->preserved($authoritative->fresh(), 'curriculum_component_id') === $protected[2], 'Uma nota, chamada ou conteúdo foi alterado.');
            AuditLog::create(['school_id' => $keep->course->academicYear->school_id, 'auditable_type' => CurriculumComponent::class,
                'auditable_id' => $keepId, 'action' => 'updated', 'metadata' => ['operation' => 'english-component-unification', 'backup_path' => $backup, 'snapshot_sha256' => $hash,
                    'grade_authority' => 'first_semester_original_second_semester_english_ii', 'removed_component_id' => $removeId]]);

            return array_replace($result, ['pending' => 0, 'backup_path' => $backup]);
        });
    }

    private function preserved($rows, string $relinked): string
    {
        return hash('sha256', json_encode($rows->map(function ($row) use ($relinked) {
            $attributes = $row->getAttributes();
            if ($row instanceof DiaryAssessment) {
                $attributes['results'] = $row->results->map->getAttributes()->all();
            }
            if ($row instanceof DiaryAttendanceRecord) {
                $attributes['entries'] = $row->entries->map->getAttributes()->all();
            }
            unset($attributes[$relinked], $attributes['updated_at']);

            return $attributes;
        })->values()->all(), JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
