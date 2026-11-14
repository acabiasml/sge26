<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Retifica transferência sem data quando a secretaria confirma não renovação. */
final class EnrollmentNonRenewalCorrection
{
    public function reconcile(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $year = $request['completed_reference_year'] ?? null;
        $this->check(($request['format'] ?? '') === 'enrollment-non-renewal-v1'
            && ($request['user_confirmed_completion_and_no_next_year_enrollment'] ?? false) === true
            && is_int($year) && $year >= 2000 && $year < (int) now()->year, 'Conclusão anual e não renovação não confirmadas.');
        $students = $request['students'] ?? [];
        $personIds = array_column($students, 'person_id');
        $ids = array_merge(array_column($students, 'regular_enrollment_id'), array_column($students, 'technical_enrollment_id'));
        $this->check(count($students) >= 1 && count($students) <= 10 && count(array_unique($personIds)) === count($students)
            && count(array_unique($ids)) === count($students) * 2, 'Vínculos ausentes ou repetidos.');
        $rows = StudentEnrollment::with('schoolClass.academicYear', 'courses')
            ->whereIn('id', $ids)->orderBy('id')->when($apply, fn ($q) => $q->lockForUpdate())->get();
        $this->check($rows->count() === count($ids), 'Há vínculo selecionado ausente.');
        $this->check(! StudentEnrollment::whereIn('person_id', $personIds)->whereHas('schoolClass.academicYear',
            fn ($q) => $q->where('school_id', $request['school_id'] ?? 0)->where('reference_year', $year + 1))->exists(), 'Existe matrícula no ano seguinte; confira antes de registrar a não renovação.');
        $through = $year.'-12-31';
        $this->check(! DB::table('diary_attendance_entries')->whereIn('student_enrollment_id', $ids)
            ->whereIn('diary_attendance_record_id', DB::table('diary_attendance_records')->whereDate('class_date', '>', $through)->select('id'))->exists(),
            'Há frequência posterior ao ano confirmado.');
        $note = 'Concluiu o ano letivo de '.$year.' e não iniciou '.($year + 1).' na escola. Matrícula não renovada para '.($year + 1).'.';
        $fact = ['completed_reference_year' => $year, 'not_enrolled_reference_year' => $year + 1,
            'attendance_ends_at' => $through, 'confirmed_by_user' => true];
        $changes = [];
        foreach ($students as $student) {
            foreach (['regular', 'technical'] as $kind) {
                $row = $rows->firstWhere('id', $student[$kind.'_enrollment_id']);
                $this->check($row->person_id === $student['person_id']
                    && $row->schoolClass->academicYear->school_id === ($request['school_id'] ?? null)
                    && $row->schoolClass->academicYear->reference_year === $year
                    && $row->courses->count() === 1
                    && $row->courses->first()->stage === ($kind === 'regular' ? AcademicCourse::STAGE_HIGH_SCHOOL : AcademicCourse::STAGE_TECHNICAL),
                    'Vínculo fora da pessoa, escola ou etapa confirmada.');
                $meta = $row->legacy_metadata ?? [];
                if ($kind === 'technical') {
                    $this->check(! $row->transferred_at && ! $row->cancelled_at && ! $row->reclassified_at
                        && (($row->status === StudentEnrollment::STATUS_TRANSFERRED && ($meta['transfer_date_pending'] ?? false) === true)
                            || ($row->status === StudentEnrollment::STATUS_NOT_RENEWED && ($meta['non_renewal'] ?? null) === $fact)),
                        'O vínculo técnico tem outra movimentação que precisa ser conferida.');
                    unset($meta['confirmed_transfer_year'], $meta['transfer_date_pending']);
                    $attributes = ['status' => StudentEnrollment::STATUS_NOT_RENEWED, 'final_result_status' => StudentEnrollment::FINAL_NOT_RENEWED,
                        'final_result_details' => ['reason' => $note, 'completed_reference_year' => $year, 'technical_course_completion_asserted' => false],
                        'notes' => $note];
                } else {
                    $this->check($row->status === StudentEnrollment::STATUS_ENROLLED && $row->final_result_status === StudentEnrollment::FINAL_APPROVED,
                        'A conclusão do ensino regular diverge da situação existente.');
                    $attributes = ['notes' => str_contains($row->notes ?? '', $note) ? $row->notes : trim(($row->notes ? $row->notes."\n" : '').$note)];
                }
                $meta['non_renewal'] = $fact;
                $attributes['legacy_metadata'] = $meta;
                $row->fill($attributes);
                if ($row->isDirty()) {
                    $changes[$row->id] = $attributes;
                }
            }
        }
        // Os modelos em memória foram preenchidos apenas para comparar os campos.
        $before = StudentEnrollment::whereIn('id', $ids)->orderBy('id')->get()->map->getAttributes()->all();
        $protected = $this->protectedDigest($ids, $personIds, $rows->pluck('school_class_id')->unique()->all());
        $hash = hash('sha256', json_encode([$request, $before, $changes, $protected], JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'enrollments_to_update' => count($changes),
            'completed_reference_year' => $year, 'not_enrolled_reference_year' => $year + 1, 'attendance_ends_at' => $through,
            'enrollment_ids' => $ids, 'note' => $note, 'protected_sha256' => $protected];
        if (! $apply) {
            return $result;
        }
        $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'Os vínculos mudaram após a conferência.');
        if (! $changes) {
            return $result;
        }
        $backup = 'diary-reconciliation/non-renewal-'.Str::uuid().'.json';
        $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before], JSON_THROW_ON_ERROR)), 'Falha ao guardar a situação anterior.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($changes): void {
            foreach ($changes as $id => $attributes) {
                StudentEnrollment::findOrFail($id)->update($attributes);
            }
        });
        $this->check(hash_equals($protected, $this->protectedDigest($ids, $personIds, $rows->pluck('school_class_id')->unique()->all())),
            'A retificação alteraria notas, presenças ou outros registros.');
        foreach ($changes as $id => $attributes) {
            AuditLog::create(['school_id' => $request['school_id'], 'auditable_type' => StudentEnrollment::class, 'auditable_id' => $id,
                'action' => 'updated', 'old_values' => collect($before)->firstWhere('id', $id), 'new_values' => $attributes,
                'metadata' => ['operation' => 'enrollment-non-renewal', 'backup_path' => $backup, 'snapshot_sha256' => $hash]]);
        }

        return $result + ['backup_path' => $backup];
    }

    private function protectedDigest(array $ids, array $personIds, array $classIds): string
    {
        return hash('sha256', json_encode([
            DB::table('student_enrollments')->whereIn('school_class_id', $classIds)->whereNotIn('id', $ids)->orderBy('id')->get(),
            DB::table('academic_course_student_enrollment')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('diary_assessment_results')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('people')->whereIn('id', $personIds)->orderBy('id')->get(),
            DB::table('person_school_roles')->whereIn('person_id', $personIds)->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
