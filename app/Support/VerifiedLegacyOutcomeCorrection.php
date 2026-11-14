<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Preserva a aprovação original documentada sem alterar notas para forçar a soma. */
final class VerifiedLegacyOutcomeCorrection
{
    public function reconcile(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $targets = $request['enrollments'] ?? [];
        $this->check(($request['format'] ?? '') === 'verified-legacy-approval-v1'
            && ($request['confirmed_by_management'] ?? false) === true
            && count($targets) >= 1 && count($targets) <= 10
            && count(array_unique(array_column($targets, 'enrollment_id'))) === count($targets), 'Aprovação original não confirmada ou duplicada.');
        $rows = StudentEnrollment::with(['student', 'schoolClass.academicYear', 'courses.components'])
            ->whereIn('id', array_column($targets, 'enrollment_id'))->orderBy('id')
            ->when($apply, fn ($q) => $q->lockForUpdate())->get();
        $this->check($rows->count() === count($targets), 'Matrícula documentada ausente.');
        $ids = $rows->modelKeys();
        $cache = StudentAcademicHistoryYear::where('source', 'system')->whereIn('student_enrollment_id', $ids)->with('records')->orderBy('id')->get();
        $before = ['enrollments' => $rows->toArray(), 'system_history' => $cache->toArray()];
        $protected = $this->protectedDigest($ids);
        $updates = [];
        foreach ($targets as $target) {
            $row = $rows->firstWhere('id', $target['enrollment_id']);
            $year = $row->schoolClass->academicYear;
            $this->check($row->person_id === ($request['person_id'] ?? null)
                && $year->school_id === ($request['school_id'] ?? null)
                && $year->reference_year === ($target['reference_year'] ?? null) && $year->isClosed()
                && $row->status === StudentEnrollment::STATUS_ENROLLED
                && $row->courses->count() === 1 && $row->courses->first()->stage === AcademicCourse::STAGE_HIGH_SCHOOL
                && $row->student->legacy_source === ($request['source'] ?? null)
                && (int) $row->student->legacy_id === ($request['legacy_student_id'] ?? null), 'Aprovação fora do estudante, escola, ano ou situação confirmada.');
            $components = $row->courses->flatMap->components->where('active', true)->pluck('id')->sort()->values()->all();
            $documents = $target['source_documents'] ?? [];
            $documentIds = array_column($documents, 'component_id');
            sort($documentIds);
            $this->check($documentIds === $components, 'É necessário comprovar todos os componentes da série.');
            foreach ($documents as $document) {
                $this->check(preg_match('/^[a-f0-9]{64}$/', $document['sha256'] ?? '')
                    && ($document['reported_result'] ?? '') === StudentEnrollment::FINAL_APPROVED
                    && is_numeric($document['reported_average'] ?? null) && $document['reported_average'] >= 6 && $document['reported_average'] <= 10,
                    'O diário não comprova aprovação em todos os componentes.');
            }
            $meta = $row->legacy_metadata ?? [];
            $meta['verified_final_result'] = ['enrollment_id' => $row->id, 'person_id' => $row->person_id,
                'school_class_id' => $row->school_class_id, 'reference_year' => $year->reference_year,
                'status' => StudentEnrollment::FINAL_APPROVED, 'confirmed_by_management' => true,
                'reason' => 'Aprovação conforme os diários originais e confirmação da gestão, respeitando o arredondamento da média final registrado na origem.',
                'source_documents' => $documents];
            $row->fill(['legacy_metadata' => $meta, 'final_result_status' => StudentEnrollment::FINAL_APPROVED,
                'final_result_details' => ['reason' => $meta['verified_final_result']['reason'], 'basis' => 'verified_legacy_result', 'source_documents' => $documents]]);
            if ($row->isDirty()) {
                $updates[$row->id] = $row->only(array_keys($row->getDirty()));
            }
        }
        $hash = hash('sha256', json_encode([$request, $before, $protected], JSON_THROW_ON_ERROR));
        $cacheChanges = $cache->filter(fn ($y) => $y->final_result !== 'Aprovado' || $y->records->contains(fn ($r) => $r->result !== 'Aprovado'))->count();
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash, 'protected_sha256' => $protected,
            'enrollments_to_update' => count($updates), 'history_years_to_update' => $cacheChanges,
            'enrollment_ids' => $ids, 'result' => StudentEnrollment::FINAL_APPROVED];
        if (! $apply) {
            return $result;
        }
        $this->check($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'Os registros mudaram após a conferência da aprovação.');
        if (! $updates && ! $cacheChanges) {
            return $result;
        }
        $backup = 'diary-reconciliation/verified-approval-'.Str::uuid().'.json';
        $this->check(Storage::disk('local')->put($backup, json_encode(['request' => $request, 'before' => $before], JSON_THROW_ON_ERROR)), 'Falha ao guardar a situação anterior.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($updates, $cache): void {
            foreach ($updates as $id => $attributes) {
                StudentEnrollment::findOrFail($id)->update($attributes + ['final_result_calculated_at' => now()]);
            }
            foreach ($cache as $year) {
                if ($year->final_result !== 'Aprovado') {
                    $year->update(['final_result' => 'Aprovado']);
                }
                $year->records()->where(fn ($q) => $q->whereNull('result')->orWhere('result', '!=', 'Aprovado'))->update(['result' => 'Aprovado']);
            }
        });
        $this->check(hash_equals($protected, $this->protectedDigest($ids)), 'A correção alteraria notas, frequência ou lançamentos manuais.');
        foreach ($rows as $row) {
            AuditLog::create(['school_id' => $request['school_id'], 'auditable_type' => StudentEnrollment::class,
                'auditable_id' => $row->id, 'action' => 'updated', 'old_values' => ['snapshot_sha256' => $hash],
                'new_values' => ['final_result_status' => StudentEnrollment::FINAL_APPROVED],
                'metadata' => ['operation' => 'verified-legacy-approval', 'backup_path' => $backup, 'source_documents' => $row->legacy_metadata['verified_final_result']['source_documents']]]);
        }

        return $result + ['backup_path' => $backup];
    }

    private function protectedDigest(array $ids): string
    {
        $people = StudentEnrollment::whereIn('id', $ids)->pluck('person_id');
        $manual = StudentAcademicHistoryYear::where('source', '!=', 'system')->whereHas('history', fn ($q) => $q->whereIn('person_id', $people))
            ->with(['records' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get();

        return hash('sha256', json_encode([
            DB::table('diary_assessment_results')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('diary_attendance_entries')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('student_period_convalidations')->whereIn('student_enrollment_id', $ids)->orderBy('id')->get(),
            DB::table('student_academic_history_records')->whereIn('student_academic_history_year_id', StudentAcademicHistoryYear::where('source', 'system')->whereIn('student_enrollment_id', $ids)->pluck('id'))
                ->orderBy('id')->get()->map(function ($row) {
                    unset($row->result, $row->updated_at);

                    return $row;
                }),
            $manual->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
