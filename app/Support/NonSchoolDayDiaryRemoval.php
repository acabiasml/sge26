<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\CalendarDay;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class NonSchoolDayDiaryRemoval
{
    public const OPERATION = 'non-school-day-diary-removal';

    public function remove(int $schoolId, int $referenceYear, array $targets, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        $this->check($schoolId > 0 && $referenceYear >= 1900 && $referenceYear <= 2100
            && count($targets) >= 1 && count($targets) <= 100, 'Escola, ano ou quantidade de registros inválidos.');
        foreach ($targets as $target) {
            foreach (['attendance_id', 'content_id', 'class_id', 'component_id', 'period_id'] as $key) {
                $this->check(is_int($target[$key] ?? null) && $target[$key] > 0, 'Identificador inválido no recorte.');
            }
            $date = $target['date'] ?? '';
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            $this->check($parsed !== false && $parsed->format('Y-m-d') === $date, 'Data inválida no recorte.');
        }
        $targets = collect($targets)->sortBy('attendance_id')->values();
        foreach (['attendance_id', 'content_id'] as $key) {
            $this->check($targets->pluck($key)->unique()->count() === $targets->count(), 'Há identificadores repetidos no recorte.');
        }

        return DB::transaction(function () use ($schoolId, $referenceYear, $targets, $apply, $expectedSnapshot): array {
            $years = AcademicYear::where('school_id', $schoolId)->where('reference_year', $referenceYear)
                ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
            $classes = SchoolClass::whereIn('id', $targets->pluck('class_id')->unique())->with(['academicYear.periods', 'courses.components'])
                ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get()->keyBy('id');
            $this->check($classes->count() === $targets->pluck('class_id')->unique()->count()
                && $classes->every(fn ($class) => $years->contains('id', $class->academic_year_id)), 'Turma fora da escola ou do ano autorizado.');
            $this->check(CalendarDay::whereIn('academic_year_id', $years->modelKeys())->where('counts_as_school_day', true)->exists(),
                'O calendário da escola não possui dias letivos cadastrados.');
            $calendar = CalendarDay::whereIn('academic_year_id', $years->modelKeys())->where(function ($query) use ($targets): void {
                foreach ($targets->pluck('date')->unique() as $date) {
                    $query->orWhereDate('date', $date);
                }
            })
                ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
            foreach ($targets as $target) {
                $class = $classes->get($target['class_id']);
                $period = $class->academicYear->periods->firstWhere('id', $target['period_id']);
                $this->check($period && $period->position >= 1 && $period->position <= 3
                    && $period->starts_at->toDateString() <= $target['date'] && $period->ends_at->toDateString() >= $target['date'],
                    'A data não pertence aos três bimestres autorizados.');
                $this->check($class->courses->flatMap->components->contains('id', $target['component_id']), 'Componente fora da matriz da turma.');
                $this->check(! $calendar->contains(fn ($day) => $day->academic_year_id === $class->academic_year_id
                    && $day->date->toDateString() === $target['date'] && $day->counts_as_school_day), 'A data está marcada como letiva. Nada foi removido.');
            }
            $attendanceIds = $targets->pluck('attendance_id')->all();
            $contentIds = $targets->pluck('content_id')->all();
            $records = DiaryAttendanceRecord::whereIn('id', $attendanceIds)->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get()->keyBy('id');
            $entries = DiaryAttendanceEntry::whereIn('diary_attendance_record_id', $attendanceIds)
                ->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
            $contents = DiaryContent::whereIn('id', $contentIds)->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get()->keyBy('id');
            $selectionHash = $this->digest([$schoolId, $referenceYear, $targets->all()]);
            if ($records->isEmpty() && $contents->isEmpty() && $entries->isEmpty()
                && AuditLog::where('school_id', $schoolId)->where('metadata->operation', self::OPERATION)
                    ->where('metadata->selection_sha256', $selectionHash)->exists()) {
                return ['mode' => $apply ? 'apply' : 'dry-run', 'already_removed' => true,
                    'attendance_records' => 0, 'attendance_entries' => 0, 'contents' => 0, 'lessons' => 0];
            }
            $this->check($records->count() === $targets->count() && $contents->count() === $targets->count(),
                'Um registro selecionado não existe mais. Execute nova conferência antes de remover.');
            foreach ($targets as $target) {
                foreach ([$records->get($target['attendance_id']), $contents->get($target['content_id'])] as $record) {
                    $this->check($record->school_class_id === $target['class_id'] && $record->curriculum_component_id === $target['component_id']
                        && $record->academic_period_id === $target['period_id'] && $record->class_date->toDateString() === $target['date'],
                        'Um registro não corresponde à turma, componente, bimestre ou data conferidos.');
                }
            }
            $classIds = array_values($classes->modelKeys());
            $protected = $this->protectedDigest($classIds, $attendanceIds, $contentIds);
            $snapshot = ['school_id' => $schoolId, 'reference_year' => $referenceYear, 'targets' => $targets->all(),
                'years' => $years->map->getAttributes()->values()->all(), 'calendar' => $calendar->map->getAttributes()->values()->all(),
                'attendance' => $records->map->getAttributes()->values()->all(), 'entries' => $entries->map->getAttributes()->values()->all(),
                'contents' => $contents->map->getAttributes()->values()->all(), 'protected_sha256' => $protected];
            $hash = $this->digest($snapshot);
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'selection_sha256' => $selectionHash,
                'school_id' => $schoolId, 'reference_year' => $referenceYear, 'attendance_records' => $records->count(),
                'attendance_entries' => $entries->count(), 'contents' => $contents->count(), 'lessons' => $records->sum('lesson_count'),
                'dates' => $targets->pluck('date')->unique()->sort()->values()->all(), 'protected_sha256' => $protected];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot),
                'Os registros ou o calendário mudaram após a conferência. Execute nova simulação.');
            $backup = 'diary-reconciliation/non-school-day-'.Str::uuid().'.json';
            $disk = Storage::disk('local');
            $this->check($disk->put($backup, json_encode($snapshot, JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            $this->check(chmod($disk->path($backup), 0600)
                && hash_equals($this->digest($snapshot), $this->digest(json_decode($disk->get($backup), true, flags: JSON_THROW_ON_ERROR))),
                'A cópia de segurança não passou na verificação. Nada foi removido.');
            $entries->each->delete();
            $records->each->delete();
            $contents->each->delete();
            $this->check(! DiaryAttendanceRecord::whereIn('id', $attendanceIds)->exists()
                && ! DiaryAttendanceEntry::whereIn('diary_attendance_record_id', $attendanceIds)->exists()
                && ! DiaryContent::whereIn('id', $contentIds)->exists(), 'A exclusão não foi concluída.');
            $this->check(hash_equals($protected, $this->protectedDigest($classIds, $attendanceIds, $contentIds)),
                'Um registro fora do recorte foi alterado. A exclusão foi revertida.');
            AuditLog::create(['school_id' => $schoolId, 'auditable_type' => SchoolClass::class, 'auditable_id' => $classIds[0],
                'action' => 'deleted', 'metadata' => ['operation' => self::OPERATION, 'selection_sha256' => $selectionHash,
                    'snapshot_sha256' => $hash, 'backup_path' => $backup, 'targets' => $targets->all(),
                    'attendance_records' => $records->count(), 'attendance_entries' => $entries->count(), 'contents' => $contents->count(),
                    'reason' => 'Remoção de conteúdo e frequência em datas não letivas solicitada pela gestão.']]);

            return $result + ['backup_path' => $backup, 'remaining_target_records' => 0];
        });
    }

    private function protectedDigest(array $classIds, array $attendanceIds, array $contentIds): string
    {
        return $this->digest([
            'attendance' => DiaryAttendanceRecord::whereIn('school_class_id', $classIds)->whereNotIn('id', $attendanceIds)
                ->with(['entries' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()->toArray(),
            'contents' => DiaryContent::whereIn('school_class_id', $classIds)->whereNotIn('id', $contentIds)->orderBy('id')->get()->toArray(),
            'assessments' => DiaryAssessment::whereIn('school_class_id', $classIds)->with(['results' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()->toArray(),
            'classes' => SchoolClass::whereIn('id', $classIds)->with(['enrollments' => fn ($q) => $q->orderBy('id'), 'courses.components'])->orderBy('id')->get()->toArray(),
        ]);
    }

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
