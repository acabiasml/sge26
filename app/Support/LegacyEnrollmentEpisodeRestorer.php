<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class LegacyEnrollmentEpisodeRestorer
{
    public function restore(array $request, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return $apply ? DB::transaction(fn () => $this->execute($request, true, $expectedSnapshot))
            : $this->execute($request, false, null);
    }

    private function execute(array $request, bool $apply, ?string $expectedSnapshot): array
    {
        $current = StudentEnrollment::with(['student', 'schoolClass.academicYear', 'courses'])
            ->when($apply, fn ($q) => $q->lockForUpdate())->findOrFail($request['current_enrollment_id'] ?? 0);
        $class = $current->schoolClass;
        $source = $current->student->legacy_source;
        $old = $request['previous_row'] ?? [];
        $new = $request['current_row'] ?? [];
        $require = static function (bool $condition, string $message): void {
            if (! $condition) {
                throw new RuntimeException($message);
            }
        };
        $require(in_array($source, ['lar', 'laura', 'beaba'], true)
            && $current->legacy_source === $source && $class->legacy_source === $source
            && $current->person_id === ($request['person_id'] ?? null)
            && $current->school_class_id === ($request['class_id'] ?? null)
            && ($request['user_confirmed_reenrollment'] ?? false) === true, 'Rematrícula fora do alvo confirmado.');
        foreach ([$old, $new] as $row) {
            $require((int) ($row['id'] ?? 0) > 0
                && (int) ($row['users_id'] ?? 0) === (int) $current->student->legacy_id
                && (int) ($row['cursos_id'] ?? 0) === (int) $class->legacy_id
                && ($row['tipo'] ?? '') === StudentEnrollment::TYPE_REGULAR, 'Identidade divergente na matrícula legada.');
        }
        $require(($old['status'] ?? '') === StudentEnrollment::STATUS_TRANSFERRED
            && ($new['status'] ?? '') === StudentEnrollment::STATUS_ENROLLED
            && $current->status === StudentEnrollment::STATUS_ENROLLED
            && (int) $old['id'] !== (int) $new['id']
            && $current->enrolled_at?->toDateString() === ($new['datamatricula'] ?? null)
            && ! $current->attendanceEndsAt(), 'As situações dos dois vínculos divergem da origem.');
        foreach ([$old['datamatricula'] ?? '', $old['datatransf'] ?? '', $new['datamatricula'] ?? ''] as $date) {
            $require((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                && Carbon::parse($date)->toDateString() === $date, 'Data de vínculo inválida.');
        }
        $require($old['datamatricula'] >= $class->academicYear->starts_at->copy()->startOfYear()->toDateString()
            && $old['datamatricula'] <= $old['datatransf'] && $old['datatransf'] < $new['datamatricula']
            && $new['datamatricula'] <= $class->academicYear->ends_at->toDateString(), 'Vínculos sobrepostos ou fora do ano.');
        $path = Storage::disk('local')->path('u810745753_'.$source.'.sql');
        $require(is_file($path) && preg_match('/^[a-f0-9]{64}$/', $request['legacy_dump_sha256'] ?? '')
            && hash_equals($request['legacy_dump_sha256'], hash_file('sha256', $path)), 'A fonte legada mudou ou não está disponível.');
        $latest = $current->legacy_metadata['latest_imported_row'] ?? [];
        foreach (['id', 'users_id', 'cursos_id', 'datamatricula', 'datatransf', 'status', 'tipo'] as $key) {
            $require(($latest[$key] ?? null) === ($new[$key] ?? null), 'O vínculo atual diverge da linha importada.');
        }
        $samePerson = StudentEnrollment::where('school_class_id', $class->id)->where('person_id', $current->person_id)->orderBy('id')->get();
        $previous = StudentEnrollment::where('legacy_source', $source)->where('legacy_id', (int) $old['id'])->where('id', '!=', $current->id)->first();
        $owner = StudentEnrollment::where('legacy_source', $source)->where('legacy_id', (int) $new['id'])->where('id', '!=', $current->id)->exists();
        $require(! $owner && in_array((int) $current->legacy_id, [(int) $old['id'], (int) $new['id']], true), 'Identificador legado já pertence a outro vínculo.');
        if ($previous) {
            $require($previous->person_id === $current->person_id && $previous->school_class_id === $class->id
                && $previous->enrolled_at?->toDateString() === $old['datamatricula']
                && $previous->transferred_at?->toDateString() === $old['datatransf']
                && $previous->status === StudentEnrollment::STATUS_TRANSFERRED, 'O vínculo anterior existente conflita com a origem.');
        }
        $require($samePerson->count() === ($previous ? 2 : 1), 'Há outros vínculos a conferir antes da restauração.');
        $before = ['enrollments' => $samePerson->toArray(), 'courses' => $current->courses->pluck('id')->sort()->values()->all(), 'request' => $request];
        $hash = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
        $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash,
            'current_enrollment_id' => $current->id, 'previous_to_create' => $previous ? 0 : 1,
            'legacy_id_to_correct' => (int) $current->legacy_id === (int) $new['id'] ? 0 : 1];
        if (! $apply) {
            return $result;
        }
        $require($expectedSnapshot && hash_equals($hash, $expectedSnapshot), 'O vínculo mudou após a conferência.');
        if (! $result['previous_to_create'] && ! $result['legacy_id_to_correct']) {
            return $result;
        }
        $backup = 'diary-reconciliation/enrollment-episodes-'.$current->id.'-'.Str::uuid().'.json';
        $require(Storage::disk('local')->put($backup, json_encode($before, JSON_THROW_ON_ERROR)), 'Falha ao salvar cópia dos vínculos.');
        chmod(Storage::disk('local')->path($backup), 0600);
        Model::withoutEvents(function () use ($current, $previous, $old, $new, $source, $request): void {
            $current->update(['legacy_id' => (int) $new['id']]);
            if (! $previous) {
                $restored = StudentEnrollment::create([
                    'school_class_id' => $current->school_class_id, 'person_id' => $current->person_id,
                    'enrolled_at' => $old['datamatricula'], 'transferred_at' => $old['datatransf'],
                    'status' => StudentEnrollment::STATUS_TRANSFERRED, 'type' => StudentEnrollment::TYPE_REGULAR,
                    'final_result_status' => StudentEnrollment::FINAL_TRANSFERRED,
                    'legacy_source' => $source, 'legacy_id' => (int) $old['id'],
                    'legacy_metadata' => ['latest_imported_row' => $old, 'restored_from_dump_sha256' => $request['legacy_dump_sha256']],
                ]);
                $restored->courses()->syncWithoutDetaching($current->courses->pluck('id')->all());
            }
        });
        AuditLog::create([
            'school_id' => $class->academicYear->school_id, 'auditable_type' => StudentEnrollment::class, 'auditable_id' => $current->id,
            'action' => 'updated', 'old_values' => ['snapshot_sha256' => $hash], 'new_values' => ['legacy_id' => (int) $new['id']],
            'metadata' => ['operation' => 'legacy-enrollment-episode-restoration', 'backup_path' => $backup, 'source_sha256' => $request['legacy_dump_sha256']],
        ]);

        return $result + ['backup_path' => $backup];
    }
}
