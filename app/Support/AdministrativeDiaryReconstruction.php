<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryExcludedDate;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Recomposição institucional: referências pedagógicas não comprovam datas originais. */
final class AdministrativeDiaryReconstruction
{
    public const SOURCE = 'administrative_diary_reconstruction';

    public function reconcile(array $manifest, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($manifest, $apply, $expectedSnapshot): array {
            $this->check(($manifest['format'] ?? '') === 'administrative-diary-reconstruction-v1'
                && ($manifest['management_confirms_classes_taught'] ?? false) === true
                && ($manifest['original_records_available'] ?? true) === false
                && ($manifest['management_authorizes_audited_reconstruction'] ?? false) === true
                && preg_match('/^[a-f0-9]{64}$/', $manifest['source_snapshot_sha256'] ?? '')
                && preg_match('/^[a-f0-9-]{36}$/', $manifest['batch_id'] ?? ''), 'Recomposição administrativa não autorizada ou sem identificação.');
            $diaries = $manifest['diaries'] ?? [];
            $this->check(is_array($diaries) && count($diaries) > 0 && count($diaries) <= 20
                && count(array_unique(array_column($diaries, 'assignment_id'))) === count($diaries), 'Recorte de diários inválido.');
            $plans = $snapshots = $summaries = [];
            $ownedIds = [];
            $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
            foreach ($diaries as $diary) {
                $assignment = SchoolClassComponent::when($apply, fn ($q) => $q->lockForUpdate())
                    ->with(['schoolClass.academicYear.periods', 'schoolClass.academicYear.days', 'schoolClass.enrollments.courses',
                        'schoolClass.startsPeriod', 'schoolClass.endsPeriod', 'component.course.startsPeriod', 'component.course.endsPeriod'])
                    ->findOrFail($diary['assignment_id'] ?? 0);
                $class = $assignment->schoolClass;
                $component = $assignment->component;
                $course = $component->course;
                $year = $class->academicYear;
                $this->check($assignment->active && $component->active
                    && $class->id === ($diary['class_id'] ?? null) && $component->id === ($diary['component_id'] ?? null)
                    && $year->school_id === ($manifest['school_id'] ?? null)
                    && $year->reference_year === ($diary['reference_year'] ?? null)
                    && $year->reference_year >= 2022 && $year->reference_year <= 2025
                    && $course->academic_year_id === $year->id && $course->class_hour_minutes > 0
                    && $class->courses()->whereKey($course->id)->exists(), 'Diário fora do recorte institucional conferido.');
                $scope = fn ($q) => $q->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id);
                $records = DiaryAttendanceRecord::where($scope)->with('entries')->orderBy('id')->get();
                $contents = DiaryContent::where($scope)->orderBy('id')->get();
                foreach ($records->concat($contents) as $row) {
                    $this->check($row->legacy_source === self::SOURCE
                        && ($row->legacy_metadata['batch_id'] ?? '') === $manifest['batch_id']
                        && ($row->legacy_metadata['manifest_sha256'] ?? '') === $manifestHash,
                        'O diário já contém registros de outra origem; nenhuma substituição automática é permitida.');
                }
                $this->check($records->count() === $records->unique(fn ($r) => $r->class_date->toDateString())->count()
                    && $contents->count() === $contents->unique(fn ($r) => $r->class_date->toDateString())->count(), 'Diário com datas duplicadas.');
                $ownedIds = array_merge($ownedIds, $records->modelKeys());
                $excluded = DiaryExcludedDate::where('school_class_component_id', $assignment->id)->get();
                $snapshots[] = [$assignment->getAttributes(), $class->getAttributes(), $year->getAttributes(),
                    $component->getAttributes(), $course->getAttributes(), $year->periods->toArray(), $year->days->toArray(),
                    $class->enrollments->sortBy('id')->values()->toArray(), $records->toArray(), $contents->toArray(), $excluded->toArray()];
                $sessions = $diary['sessions'] ?? [];
                $this->check(is_array($sessions) && count($sessions) > 0 && count($sessions) <= 250
                    && count(array_unique(array_column($sessions, 'date'))) === count($sessions), 'Datas de recomposição inválidas.');
                $this->check($records->every(fn ($r) => in_array($r->class_date->toDateString(), array_column($sessions, 'date'), true))
                    && $contents->every(fn ($r) => in_array($r->class_date->toDateString(), array_column($sessions, 'date'), true)), 'Há registros não contemplados no lote.');
                $lessons = 0;
                foreach ($sessions as $session) {
                    $date = $session['date'] ?? '';
                    $count = $session['lesson_count'] ?? 0;
                    $text = trim((string) ($session['content'] ?? ''));
                    $this->check(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                        && CarbonImmutable::parse($date)->toDateString() === $date && $date <= now()->toDateString()
                        && is_int($count) && $count > 0 && $count <= 7 && $text !== '' && mb_strlen($text) <= 12000, 'Data, conteúdo ou quantidade inválida.');
                    $periods = $year->periods->filter(fn ($p) => $p->starts_at->toDateString() <= $date && $p->ends_at->toDateString() >= $date);
                    $this->check($periods->count() === 1, 'Data sem período letivo único.');
                    $period = $periods->sole();
                    $this->check($component->isActiveInPeriod($period)
                        && (! $class->startsPeriod || $period->position >= $class->startsPeriod->position)
                        && (! $class->endsPeriod || $period->position <= $class->endsPeriod->position)
                        && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
                        && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position)
                        && $year->days->contains(fn ($d) => $d->counts_as_school_day && $d->date->toDateString() === $date)
                        && ! $excluded->contains(fn ($d) => $d->class_date->toDateString() === $date), 'Data fora do calendário ou da vigência curricular.');
                    $references = $session['content_references'] ?? [];
                    $this->check(is_array($references) && count($references) > 0 && count($references) <= 10, 'Conteúdo sem referência pedagógica identificada.');
                    foreach ($references as $reference) {
                        $this->check(is_string($reference['file'] ?? null)
                            && preg_match('/^[a-f0-9]{64}$/', $reference['sha256'] ?? '')
                            && ($reference['used_as_reference_only'] ?? false) === true, 'A referência não pode ser apresentada como comprovação da aula original.');
                    }
                    $eligible = $class->enrollments->filter(fn ($e) => $e->courses->contains('id', $course->id) && $this->eligible($e, $date));
                    $this->check($eligible->isNotEmpty() && $eligible->count() === $eligible->unique('person_id')->count(), 'Sem turma elegível ou matrículas sobrepostas.');
                    $roster = $session['roster'] ?? [];
                    $expectedIds = $eligible->modelKeys();
                    $rosterIds = array_column($roster, 'enrollment_id');
                    sort($expectedIds);
                    sort($rosterIds);
                    $this->check($expectedIds === $rosterIds, 'A relação de estudantes diverge dos vínculos vigentes.');
                    $record = $records->first(fn ($r) => $r->class_date->toDateString() === $date);
                    $storedContent = $contents->first(fn ($r) => $r->class_date->toDateString() === $date);
                    $this->check(! $record || ($record->lesson_count === $count && $record->academic_period_id === $period->id), 'Chamada do lote foi alterada.');
                    $this->check(! $storedContent || ($storedContent->content === $text && $storedContent->academic_period_id === $period->id), 'Conteúdo do lote foi alterado.');
                    $plans[] = compact('assignment', 'date', 'count', 'text', 'references', 'eligible', 'roster', 'record', 'storedContent', 'period');
                    $lessons += $count;
                }
                $this->check($lessons === ($diary['expected_lessons'] ?? null)
                    && abs($lessons * $course->class_hour_minutes / 60 - $component->calculatedWorkloadHours()) < .01,
                    'A recomposição diverge da carga prevista na matriz.');
                $summaries[] = ['class_id' => $class->id, 'component_id' => $component->id,
                    'year' => $year->reference_year, 'sessions' => count($sessions), 'lessons' => $lessons];
            }
            [$evidence, $load, $sourceSnapshot] = $this->sources($plans, $ownedIds, $manifest['school_id']);
            $snapshots[] = $sourceSnapshot;
            $stats = ['calls_to_create' => 0, 'contents_to_create' => 0, 'entries_to_create' => 0,
                'present_entries' => 0, 'absent_entries' => 0, 'unrecorded_entries' => 0];
            $touched = [];
            foreach ($plans as &$plan) {
                $stats['calls_to_create'] += $plan['record'] ? 0 : 1;
                $stats['contents_to_create'] += $plan['storedContent'] ? 0 : 1;
                $entries = [];
                $unrecorded = [];
                foreach ($plan['roster'] as $item) {
                    $enrollment = $plan['eligible']->find($item['enrollment_id']);
                    $key = $enrollment->person_id.':'.$plan['date'];
                    [$status, $sourceIds] = $this->classify($evidence[$key] ?? []);
                    $claimed = $item['source_record_ids'] ?? [];
                    sort($claimed);
                    $this->check(($item['status'] ?? null) === $status && $claimed === $sourceIds, 'Frequência ou evidência do dia mudou; recalcule a proposta.');
                    $load[$key] = ($load[$key] ?? 0) + $plan['count'] * $plan['assignment']->component->course->class_hour_minutes;
                    $touched[$key] = true;
                    $this->check($load[$key] <= 420, 'A recomposição excede sete horas diárias para um estudante.');
                    if ($status === null) {
                        $unrecorded[] = $enrollment->id;
                        $stats['unrecorded_entries']++;

                        continue;
                    }
                    $entries[] = ['student_enrollment_id' => $enrollment->id, 'status' => $status,
                        'attended_lessons' => $status === DiaryAttendanceRecord::STATUS_PRESENT ? $plan['count'] : 0,
                        'lesson_presence' => array_fill(0, $plan['count'], $status === DiaryAttendanceRecord::STATUS_PRESENT)];
                    $stats[$status === DiaryAttendanceRecord::STATUS_PRESENT ? 'present_entries' : 'absent_entries']++;
                }
                if ($plan['record']) {
                    $current = $plan['record']->entries->sortBy('student_enrollment_id')->map->only(['student_enrollment_id', 'status', 'attended_lessons', 'lesson_presence'])->values()->all();
                    $expected = collect($entries)->sortBy('student_enrollment_id')->values()->all();
                    $this->check($current === $expected, 'Há edição posterior de frequência; preservar e conferir manualmente.');
                } else {
                    $stats['entries_to_create'] += count($entries);
                }
                $plan['entries'] = $entries;
                $plan['unrecorded'] = $unrecorded;
            }
            unset($plan);
            $hash = hash('sha256', json_encode([$manifest, $snapshots], JSON_THROW_ON_ERROR));
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'provenance' => self::SOURCE, 'snapshot_sha256' => $hash,
                'manifest_sha256' => $manifestHash, 'diaries' => $summaries, 'stats' => $stats,
                'daily_limit_minutes' => 420, 'maximum_daily_minutes' => max(array_map(fn ($k) => $load[$k], array_keys($touched)))];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'Os registros mudaram desde a simulação.');
            if ($stats['calls_to_create'] + $stats['contents_to_create'] + $stats['entries_to_create'] === 0) {
                return $result + ['idempotent' => true];
            }
            $backup = 'diary-reconciliation/administrative-'.Str::uuid().'.json';
            $this->check(Storage::disk('local')->put($backup, json_encode(['manifest' => $manifest, 'before' => $snapshots], JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            chmod(Storage::disk('local')->path($backup), 0600);
            Model::withoutEvents(function () use ($plans, $manifest, $manifestHash): void {
                foreach ($plans as $plan) {
                    $base = ['school_class_id' => $plan['assignment']->school_class_id,
                        'curriculum_component_id' => $plan['assignment']->curriculum_component_id,
                        'academic_period_id' => $plan['period']->id, 'class_date' => $plan['date'],
                        'legacy_source' => self::SOURCE,
                        'legacy_metadata' => ['provenance' => self::SOURCE, 'batch_id' => $manifest['batch_id'], 'manifest_sha256' => $manifestHash,
                            'administrative_record_key' => $manifest['batch_id'].':'.$plan['assignment']->id.':'.$plan['date'],
                            'original_records_available' => false, 'original_event_dates_confirmed' => false,
                            'institutional_reconstruction_authorized' => true, 'content_references' => $plan['references'],
                            'attendance_rule' => 'same_school_boarding_daily_absence_prevails',
                            'attendance_evidence' => $plan['roster'], 'unrecorded_enrollment_ids' => $plan['unrecorded']]];
                    if (! $plan['storedContent']) {
                        DiaryContent::create($base + ['content' => $plan['text']]);
                    }
                    if (! $plan['record']) {
                        $record = DiaryAttendanceRecord::create($base + ['lesson_count' => $plan['count']]);
                        foreach ($plan['entries'] as $entry) {
                            $record->entries()->create($entry);
                        }
                    }
                }
            });
            $creditChanges = [];
            $sourceClasses = collect($plans)->pluck('assignment.school_class_id')->unique();
            foreach (StudentEnrollment::whereNotNull('reclassified_from_enrollment_id')
                ->whereHas('reclassifiedFrom', fn ($q) => $q->whereIn('school_class_id', $sourceClasses))->get() as $destination) {
                $creditChanges = array_merge($creditChanges, app(ReclassificationAttendanceCredits::class)->synchronize($destination->reclassifiedFrom, $destination));
            }
            foreach ($diaries as $diary) {
                AuditLog::create(['school_id' => $manifest['school_id'], 'auditable_type' => SchoolClassComponent::class,
                    'auditable_id' => $diary['assignment_id'], 'action' => 'updated', 'metadata' => [
                        'operation' => self::SOURCE, 'batch_id' => $manifest['batch_id'], 'manifest_sha256' => $manifestHash,
                        'original_records_available' => false, 'original_event_dates_confirmed' => false,
                        'backup_path' => $backup, 'snapshot_sha256' => $hash, 'stats' => $stats]]);
            }

            return $result + ['backup_path' => $backup, 'credit_changes' => $creditChanges];
        });
    }

    private function eligible(StudentEnrollment $enrollment, string $date): bool
    {
        return $enrollment->enrolled_at && $enrollment->includesAttendanceDate(CarbonImmutable::parse($date))
            && (! in_array($enrollment->status, ['transferido', 'cancelado', 'reclassificado', 'nao_renovado'], true) || $enrollment->attendanceEndsAt());
    }

    /** Carga conservadora em todas as escolas; evidência de internato somente na escola-alvo. */
    private function sources(array $plans, array $ownedIds, int $schoolId): array
    {
        $people = collect($plans)->flatMap(fn ($p) => $p['eligible']->pluck('person_id'))->unique();
        $enrollments = StudentEnrollment::whereIn('person_id', $people)->with('courses')->orderBy('id')->get();
        $byClass = $enrollments->groupBy('school_class_id');
        $records = DiaryAttendanceRecord::whereIn('school_class_id', $enrollments->pluck('school_class_id')->unique())
            ->where(function ($query) use ($plans): void {
                foreach (array_unique(array_column($plans, 'date')) as $date) {
                    $query->orWhereDate('class_date', $date);
                }
            })
            ->with(['component.course.academicYear', 'entries' => fn ($q) => $q->whereIn('student_enrollment_id', $enrollments->modelKeys())])
            ->orderBy('id')->get();
        $evidence = $load = $seen = [];
        foreach ($records as $record) {
            if (in_array($record->id, $ownedIds, true)) {
                continue;
            }
            $minutes = $record->component->course->class_hour_minutes ?: $record->component->course->academicYear->class_hour_minutes;
            $this->check($minutes > 0 && $record->lesson_count > 0, 'Há chamada sem quantidade ou duração para conferir o limite diário.');
            foreach ($byClass[$record->school_class_id] as $enrollment) {
                if (! $enrollment->includesAttendanceDate($record->class_date)) {
                    continue;
                }
                $entry = $record->entries->firstWhere('student_enrollment_id', $enrollment->id);
                if (! $enrollment->courses->contains('id', $record->component->academic_course_id) && ! $entry) {
                    continue;
                }
                $key = $enrollment->person_id.':'.$record->class_date->toDateString();
                $dedup = $enrollment->person_id.':'.$record->id;
                if (! isset($seen[$dedup])) {
                    $load[$key] = ($load[$key] ?? 0) + $minutes * $record->lesson_count;
                    $seen[$dedup] = true;
                }
                if ($entry && $record->component->course->academicYear->school_id === $schoolId && $record->legacy_source !== self::SOURCE) {
                    $evidence[$key][] = [$record->id, $entry, $record->lesson_count];
                }
            }
        }

        return [$evidence, $load, [$enrollments->toArray(), $records->map(fn ($r) => [$r->getAttributes(), $r->entries->toArray(), $r->component->course->getAttributes()])->all()]];
    }

    private function classify(array $items): array
    {
        $absent = $present = [];
        foreach ($items as [$id, $entry, $count]) {
            if (in_array($entry->status, ['falta', 'ausente', 'justificada'], true)
                || ($entry->attended_lessons !== null && $entry->attended_lessons < $count)
                || in_array(false, $entry->lesson_presence ?? [], true)) {
                $absent[] = $id;
            } elseif (in_array($entry->status, ['presente', 'presenca'], true)
            || ($entry->attended_lessons !== null && $entry->attended_lessons >= $count)) {
                $present[] = $id;
            }
        }
        $ids = array_values(array_unique($absent ?: $present));
        sort($ids);

        return [$absent ? DiaryAttendanceRecord::STATUS_ABSENT : ($present ? DiaryAttendanceRecord::STATUS_PRESENT : null), $ids];
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
