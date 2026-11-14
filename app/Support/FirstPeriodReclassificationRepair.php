<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Retificação administrativa confirmada, sem reconstruir chamadas inexistentes. */
final class FirstPeriodReclassificationRepair
{
    public function reconcile(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        if (($request['format'] ?? '') !== 'first-period-reclassification-repair-v1' || ($request['confirmed_by_management'] ?? false) !== true) {
            throw new RuntimeException('Retificação de reclassificação não confirmada.');
        }
        DB::beginTransaction();
        try {
            $origin = StudentEnrollment::with('schoolClass.academicYear.periods')->findOrFail($request['origin_enrollment_id']);
            $destination = StudentEnrollment::with('schoolClass.academicYear.periods')->findOrFail($request['destination_enrollment_id']);
            $year = $origin->schoolClass->academicYear;
            $first = $year->periods->firstWhere('position', 1);
            $second = $year->periods->firstWhere('position', 2);
            if ($origin->status !== StudentEnrollment::STATUS_RECLASSIFIED || ! $first || ! $second
                || $origin->person_id !== $destination->person_id || $origin->school_class_id === $destination->school_class_id
                || $destination->schoolClass->academic_year_id !== $year->id
                || $year->school_id !== $request['school_id'] || $year->reference_year !== $request['reference_year']
                || ! in_array($destination->reclassified_from_enrollment_id, [null, $origin->id], true)) {
                throw new RuntimeException('Origem e destino fora da retificação confirmada.');
            }
            $effective = $second->starts_at->toDateString();
            if (($origin->reclassified_at && $origin->reclassified_at->toDateString() !== $effective)
                || ($origin->transferred_at && $origin->transferred_at->toDateString() !== $effective)
                || ($destination->enrolled_at && ! in_array($destination->enrolled_at->toDateString(), [$first->starts_at->toDateString(), $effective], true))) {
                throw new RuntimeException('Há uma data específica divergente; não substituir automaticamente.');
            }
            $ids = [$origin->id, $destination->id];
            $entries = DiaryAttendanceEntry::whereIn('student_enrollment_id', $ids)->with('record')->orderBy('id')->get();
            $credits = StudentPeriodConvalidation::whereIn('student_enrollment_id', $ids)->orderBy('id')->get();
            $protected = $this->gradesDigest($ids);
            $before = ['request' => $request, 'origin' => $origin->getAttributes(), 'destination' => $destination->getAttributes(),
                'entries' => $entries->map->getAttributes()->all(), 'credits' => $credits->map->getAttributes()->all(),
                'periods' => [$first->getAttributes(), $second->getAttributes()], 'grades_sha256' => $protected];
            $hash = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
            if ($apply && (! $expectedSnapshot || ! hash_equals($hash, $expectedSnapshot))) {
                throw new RuntimeException('Matrículas ou chamadas mudaram desde a conferência.');
            }
            $backup = null;
            if ($apply) {
                $backup = 'diary-reconciliation/reclassification-'.Str::uuid().'.json';
                if (! Storage::disk('local')->put($backup, json_encode($before, JSON_THROW_ON_ERROR))) { throw new RuntimeException('Falha na cópia de segurança.'); }
                chmod(Storage::disk('local')->path($backup), 0600);
            }
            $result = Model::withoutEvents(function () use ($origin, $destination, $effective, $entries, $request): array {
                $dates = 0;
                $basis = ['origin_enrollment_id' => $origin->id, 'destination_enrollment_id' => $destination->id,
                    'effective_at' => $effective, 'decision_period' => 1, 'basis' => 'management_confirmed_default_start_second_period'];
                foreach ([[$origin, ['reclassified_at' => $effective]], [$destination, ['enrolled_at' => $effective, 'reclassified_at' => $effective,
                    'reclassified_from_enrollment_id' => $origin->id]]] as [$row, $attributes]) {
                    $attributes['legacy_metadata'] = array_merge($row->legacy_metadata ?? [], ['administrative_reclassification' => $basis]);
                    $row->fill($attributes);
                    if ($row->isDirty()) { $row->save(); $dates++; }
                }
                $removed = 0;
                foreach ($entries as $entry) {
                    $row = $entry->student_enrollment_id === $origin->id ? $origin : $destination;
                    if (! $row->includesAttendanceDate($entry->record->class_date)) { $entry->delete(); $removed++; }
                }
                $remaining = DiaryAttendanceEntry::whereIn('student_enrollment_id', [$origin->id, $destination->id])->with('record')->get();
                $absences = 0;
                foreach ($remaining->groupBy(fn ($e) => $e->record->class_date->toDateString()) as $day) {
                    if (! $day->contains(fn ($e) => $e->status === DiaryAttendanceRecord::STATUS_ABSENT
                        || ($e->attended_lessons !== null && $e->attended_lessons < $e->record->lesson_count))) { continue; }
                    foreach ($day as $entry) {
                        if ($entry->status === DiaryAttendanceRecord::STATUS_EXCUSED) { continue; }
                        if ($entry->status !== DiaryAttendanceRecord::STATUS_ABSENT || $entry->attended_lessons !== 0) {
                            $entry->update(['status' => DiaryAttendanceRecord::STATUS_ABSENT, 'attended_lessons' => 0,
                                'lesson_presence' => array_fill(0, (int) $entry->record->lesson_count, false)]);
                            $absences++;
                        }
                    }
                }
                $creditChanges = app(ReclassificationAttendanceCredits::class)->synchronize($origin->fresh(), $destination->fresh());
                $report = app(StudentReportCardBuilder::class)->build($destination->fresh());

                return ['enrollments_to_update' => $dates, 'outside_window_entries_removed' => $removed,
                    'boarding_absences_aligned' => $absences, 'credits_to_update' => count($creditChanges), 'credits' => $creditChanges,
                    'origin_end' => $origin->fresh()->attendanceEndsAt(), 'destination_start' => $destination->fresh()->enrolled_at->toDateString(),
                    'destination_end' => $destination->fresh()->attendanceEndsAt(), 'destination_annual_attendance' => $report['annualAttendance'],
                    'destination_credit_only_components' => $report['attendanceCreditComponents']->map(fn ($c) => [
                        'name' => $c['component']->name, 'completed_minutes' => $c['attendance']['diary_attended_minutes'] ?? null]),
                ];
            });
            if (! hash_equals($protected, $this->gradesDigest($ids))) { throw new RuntimeException('As notas originais seriam alteradas.'); }
            if ($apply) {
                if ($result['enrollments_to_update'] || $result['outside_window_entries_removed'] || $result['boarding_absences_aligned'] || $result['credits_to_update']) {
                    AuditLog::create(['school_id' => $year->school_id, 'auditable_type' => StudentEnrollment::class, 'auditable_id' => $destination->id,
                        'action' => 'updated', 'new_values' => $result, 'metadata' => ['operation' => 'first-period-reclassification-repair',
                            'backup_path' => $backup, 'snapshot_sha256' => $hash, 'management_confirmed' => true]]);
                }
                DB::commit();
            } else { DB::rollBack(); }

            return ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'grades_sha256' => $protected,
                'effective_at' => $effective, 'backup_path' => $backup] + $result;
        } catch (\Throwable $error) { DB::rollBack(); throw $error; }
    }

    private function gradesDigest(array $ids): string
    {
        return hash('sha256', json_encode([
            DB::table('diary_assessment_results')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('student_enrollments')->whereIn('id', $ids)->orderBy('id')->get(['id', 'status', 'final_result_status', 'final_result_details']),
        ], JSON_THROW_ON_ERROR));
    }
}
