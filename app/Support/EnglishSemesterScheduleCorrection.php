<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class EnglishSemesterScheduleCorrection
{
    public function correct(int $scheduleId, int $sourceId, int $targetId, int $weeklyTotal, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($scheduleId, $sourceId, $targetId, $weeklyTotal, $apply, $expectedSnapshot): array {
            $schedule = SchoolClassSchedule::with('slots')->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($scheduleId);
            $assignments = SchoolClassComponent::with(['component.course.components', 'component.startsPeriod', 'component.endsPeriod'])
                ->whereIn('id', [$sourceId, $targetId])->when($apply, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
            $source = $assignments->get($sourceId);
            $target = $assignments->get($targetId);
            $this->check($source && $target && $sourceId !== $targetId
                && $source->school_class_id === $schedule->school_class_id && $target->school_class_id === $schedule->school_class_id
                && $source->teacher_person_id !== null && $source->teacher_person_id === $target->teacher_person_id
                && $source->component->academic_course_id === $target->component->academic_course_id
                && $source->component->course->stage === AcademicCourse::STAGE_HIGH_SCHOOL
                && Str::of($source->component->name)->ascii()->lower()->startsWith('lingua inglesa')
                && Str::of($target->component->name)->ascii()->lower()->startsWith('lingua inglesa')
                && $source->component->knowledge_area_id === $target->component->knowledge_area_id,
                'Turma, matriz, área ou professora divergentes para a correção.');
            $this->check($source->component->endsPeriod && $target->component->startsPeriod && $target->component->endsPeriod
                && $source->component->endsPeriod->ends_at->lt($target->component->startsPeriod->starts_at)
                && $schedule->starts_at->gte($target->component->startsPeriod->starts_at)
                && $schedule->starts_at->lte($target->component->endsPeriod->ends_at)
                && $target->component->weekly_lessons === 1 && $weeklyTotal === 14,
                'Os períodos ou as quantidades não correspondem à redução do segundo semestre.');
            $candidates = $schedule->slots->where('type', 'aula')->whereIn('school_class_component_id', [$sourceId, $targetId])
                ->sortBy(fn ($slot) => sprintf('%d-%d-%s-%010d', $slot->school_class_component_id === $targetId ? 0 : 1,
                    $slot->weekday, $slot->starts_at, $slot->id))->values();
            $this->check($candidates->count() >= 1 && $candidates->count() <= 2, 'A quantidade de horários de Inglês mudou; confira a grade.');
            foreach ($candidates as $slot) {
                $minutes = static function (string $time): int {
                    $parts = explode(':', $time);

                    return (int) $parts[0] * 60 + (int) $parts[1];
                };
                $this->check($minutes($slot->ends_at) - $minutes($slot->starts_at) === 50, 'Há um horário de Inglês com duração diferente de cinquenta minutos.');
            }
            $keep = $candidates->first();
            $remove = $candidates->slice(1);
            $newTotal = $schedule->slots->where('type', 'aula')->count() - $remove->count();
            $this->check($newTotal === $weeklyTotal, 'O total das outras disciplinas não corresponde à matriz.');
            $course = $source->component->course;
            $protected = $this->protectedState($schedule->school_class_id, $course->id);
            $protectedHash = hash('sha256', json_encode($protected, JSON_THROW_ON_ERROR));
            $snapshot = ['schedule' => $schedule->getAttributes(), 'slots' => $schedule->slots->sortBy('id')->map->getAttributes()->values()->all(),
                'assignments' => $assignments->sortKeys()->map->getAttributes()->values()->all(), 'protected' => $protected];
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $reassign = $keep->school_class_component_id !== $targetId;
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'schedule_id' => $scheduleId,
                'class_id' => $schedule->school_class_id, 'teacher_person_id' => $target->teacher_person_id,
                'slots_to_reassign' => $reassign ? 1 : 0, 'slots_to_remove' => $remove->count(),
                'weekly_classes_before' => $schedule->slots->where('type', 'aula')->count(), 'weekly_classes_after' => $newTotal,
                'annual_hours' => $course->calculatedWorkloadHours(),
                'retained_time' => $keep->only(['weekday', 'starts_at', 'ends_at']), 'protected_sha256' => $protectedHash,
                'english_records_by_period' => collect([$source->curriculum_component_id, $target->curriculum_component_id])->mapWithKeys(fn ($id) => [$id => [
                    'attendance_lessons' => collect($protected['attendance'])->where('curriculum_component_id', $id)->groupBy('academic_period_id')->map(fn ($rows) => $rows->sum('lesson_count')),
                    'contents' => collect($protected['contents'])->where('curriculum_component_id', $id)->groupBy('academic_period_id')->map->count(),
                    'grades' => collect($protected['assessments'])->where('curriculum_component_id', $id)->groupBy('academic_period_id')->map(fn ($rows) => $rows->sum(fn ($row) => collect($row['results'])->whereNotNull('score')->count())),
                ]])];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot), 'O horário ou os registros mudaram após a conferência. Execute nova simulação.');
            if (! $reassign && $remove->isEmpty()) {
                return $result;
            }
            $backup = 'diary-reconciliation/english-schedule-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode($snapshot, JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            if ($reassign) {
                $keep->update(['school_class_component_id' => $targetId]);
            }
            foreach ($remove as $slot) {
                $slot->delete();
            }
            $this->check($protectedHash === hash('sha256', json_encode($this->protectedState($schedule->school_class_id, $course->id), JSON_THROW_ON_ERROR)),
                'Uma carga, vínculo ou lançamento escolar foi alterado.');
            AuditLog::create(['school_id' => $course->academicYear->school_id, 'auditable_type' => SchoolClassSchedule::class,
                'auditable_id' => $scheduleId, 'action' => 'updated', 'metadata' => ['operation' => 'english-semester-schedule',
                    'backup_path' => $backup, 'snapshot_sha256' => $hash, 'protected_sha256' => $protectedHash]]);

            return $result + ['backup_path' => $backup];
        });
    }

    private function protectedState(int $classId, int $courseId): array
    {
        $course = AcademicCourse::with(['components' => fn ($q) => $q->orderBy('id'), 'academicYear.periods', 'academicYear.days'])->findOrFail($courseId);

        return ['course' => $course->toArray(),
            'enrollments' => $course->classes()->findOrFail($classId)->enrollments()->orderBy('id')->get()->toArray(),
            'assessments' => DiaryAssessment::where('school_class_id', $classId)->with(['results' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()->toArray(),
            'attendance' => DiaryAttendanceRecord::where('school_class_id', $classId)->with(['entries' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()->toArray(),
            'contents' => DiaryContent::where('school_class_id', $classId)->orderBy('id')->get()->toArray()];
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
