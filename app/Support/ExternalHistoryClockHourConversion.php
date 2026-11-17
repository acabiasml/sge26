<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ExternalHistoryClockHourConversion
{
    public const OPERATION = 'external-history-clock-hours';

    public const NOTE = 'Carga horária recebida em horas-aula de 50 minutos e convertida para horas-relógio (60 minutos): FGB de 960 h/a para 800 h; Itinerário Formativo de 240 h/a para 200 h; total de 1.200 h/a para 1.000 h.';

    public function convert(int $personId, int $historyId, int $yearId, bool $apply = false, ?string $expectedSnapshot = null): array
    {
        return DB::transaction(function () use ($personId, $historyId, $yearId, $apply, $expectedSnapshot): array {
            $history = StudentAcademicHistory::where('person_id', $personId)->whereKey($historyId)
                ->when($apply, fn ($q) => $q->lockForUpdate())->firstOrFail();
            $year = $history->years()->whereKey($yearId)->when($apply, fn ($q) => $q->lockForUpdate())->firstOrFail();
            $this->check($history->education_stage === 'medio' && $year->source === 'external'
                && $year->student_enrollment_id === null && $year->transcript_mode === 'detailed'
                && ($year->grade_number === 1 || preg_match('/^1\s*[º°ª]?\s*(ano|s[eé]rie)$/iu', trim($year->label))),
                'A conversão exige o primeiro ano externo e detalhado do Ensino Médio selecionado.');
            $records = $year->records()->with('component')->when($apply, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
            $this->check($records->isNotEmpty() && $records->count() <= 100
                && $records->every(fn ($r) => $r->component->student_academic_history_id === $historyId
                    && in_array($r->component->formation, ['Formação Geral Básica', 'Itinerário Formativo'], true)
                    && $r->workload_hours !== null && (float) $r->workload_hours >= 0), 'Há componentes ou cargas incompatíveis com a conversão.');
            $groups = $records->groupBy(fn ($r) => $r->component->formation);
            $beforeHours = $groups->map(fn ($rows) => $rows->sum(fn ($r) => $this->cents($r->workload_hours)));
            $already = $this->cents($year->workload_hours) === 100000
                && $beforeHours->get('Formação Geral Básica') === 80000 && $beforeHours->get('Itinerário Formativo') === 20000
                && str_contains((string) $year->notes, self::NOTE)
                && AuditLog::where('auditable_type', StudentAcademicHistoryYear::class)->where('auditable_id', $yearId)
                    ->where('metadata->operation', self::OPERATION)->exists();
            if ($already) {
                return ['mode' => $apply ? 'apply' : 'dry-run', 'already_converted' => true, 'year_id' => $yearId,
                    'fgb_hours' => 800, 'itinerary_hours' => 200, 'total_hours' => 1000];
            }
            $this->check($this->cents($year->workload_hours) === 120000
                && $beforeHours->get('Formação Geral Básica') === 96000 && $beforeHours->get('Itinerário Formativo') === 24000,
                'As cargas de origem não correspondem a 960 h/a de FGB e 240 h/a de itinerário. Execute nova conferência.');
            $snapshot = ['history' => $history->getAttributes(), 'year' => $year->getAttributes(),
                'records' => $records->map->getAttributes()->all(), 'components' => $records->map(fn ($r) => $r->component->getAttributes())->all()];
            $hash = $this->digest($snapshot);
            $plan = [];
            foreach ($groups as $formation => $rows) {
                $allocation = [];
                foreach ($rows as $record) {
                    $numerator = $this->cents($record->workload_hours) * 50;
                    $allocation[$record->id] = ['value' => intdiv($numerator, 60), 'remainder' => $numerator % 60];
                }
                $target = $formation === 'Formação Geral Básica' ? 80000 : 20000;
                $left = $target - array_sum(array_column($allocation, 'value'));
                $ranked = array_keys($allocation);
                usort($ranked, fn ($a, $b) => ($allocation[$b]['remainder'] <=> $allocation[$a]['remainder']) ?: ($a <=> $b));
                $this->check($left >= 0 && $left <= count($ranked), 'O arredondamento não preserva o total da formação.');
                for ($i = 0; $i < $left; $i++) {
                    $allocation[$ranked[$i]]['value']++;
                }
                foreach ($rows as $record) {
                    $hours = $this->decimal($allocation[$record->id]['value']);
                    $completed = $record->completed_workload_hours;
                    if ($completed !== null) {
                        $completed = $this->cents($completed) === $this->cents($record->workload_hours)
                            ? $hours : $this->decimal((int) round($this->cents($completed) * 50 / 60));
                    }
                    $plan[$record->id] = ['name' => $record->component->name, 'formation' => $formation,
                        'before' => $record->workload_hours, 'after' => $hours, 'completed_after' => $completed];
                }
            }
            ksort($plan);
            $result = ['mode' => $apply ? 'apply' : 'dry-run', 'snapshot_sha256' => $hash,
                'person_id' => $personId, 'history_id' => $historyId, 'year_id' => $yearId, 'year' => $year->year,
                'fgb_hours' => 800, 'itinerary_hours' => 200, 'total_hours' => 1000, 'record_changes' => $plan, 'note' => self::NOTE];
            if (! $apply) {
                return $result;
            }
            $this->check($expectedSnapshot !== null && hash_equals($hash, $expectedSnapshot),
                'O histórico mudou após a conferência. Execute nova simulação.');
            $protected = $this->protectedDigest($personId, $yearId);
            $backup = 'diary-reconciliation/external-history-clock-hours-'.Str::uuid().'.json';
            $disk = Storage::disk('local');
            $this->check($disk->put($backup, json_encode($snapshot, JSON_THROW_ON_ERROR)), 'Não foi possível guardar a cópia de segurança.');
            $this->check(chmod($disk->path($backup), 0600)
                && $hash === $this->digest(json_decode($disk->get($backup), true, flags: JSON_THROW_ON_ERROR)), 'A cópia de segurança não passou na verificação.');
            foreach ($records as $record) {
                $change = $plan[$record->id];
                $record->update(['workload_hours' => $change['after'], 'completed_workload_hours' => $change['completed_after']]);
            }
            $changes = ['workload_hours' => '1000.00', 'notes' => trim((string) $year->notes) === ''
                ? self::NOTE : rtrim($year->notes)."\n".self::NOTE];
            if ($year->formation_workload_hours !== null) {
                $changes['formation_workload_hours'] = ['Formação Geral Básica' => 800, 'Itinerário Formativo' => 200];
            }
            $year->update($changes);
            $after = $year->fresh('records.component');
            $totals = $after->records->groupBy(fn ($r) => $r->component->formation)
                ->map(fn ($rows) => $rows->sum(fn ($r) => $this->cents($r->workload_hours)));
            $this->check($totals->get('Formação Geral Básica') === 80000 && $totals->get('Itinerário Formativo') === 20000
                && $this->cents($after->workload_hours) === 100000, 'Os totais convertidos não conferem. A alteração foi revertida.');
            foreach ($after->records as $record) {
                $original = collect($snapshot['records'])->firstWhere('id', $record->id);
                $this->check($this->without($original, ['workload_hours', 'completed_workload_hours', 'updated_at'])
                    === $this->without($record->getAttributes(), ['workload_hours', 'completed_workload_hours', 'updated_at']), 'Uma nota ou outro dado do componente foi alterado.');
            }
            $this->check($this->without($snapshot['year'], ['workload_hours', 'formation_workload_hours', 'notes', 'updated_at'])
                === $this->without($after->getAttributes(), ['workload_hours', 'formation_workload_hours', 'notes', 'updated_at'])
                && hash_equals($protected, $this->protectedDigest($personId, $yearId)), 'Um dado fora da conversão foi alterado.');
            AuditLog::create(['school_id' => $history->school_id, 'auditable_type' => StudentAcademicHistoryYear::class,
                'auditable_id' => $yearId, 'action' => 'updated', 'metadata' => ['operation' => self::OPERATION,
                    'person_id' => $personId, 'history_id' => $historyId, 'source_minutes' => 50, 'target_minutes' => 60,
                    'snapshot_sha256' => $hash, 'backup_path' => $backup, 'record_changes' => $plan,
                    'rounding' => 'Maiores restos por formação, com desempate pelo identificador do registro.']]);

            return $result + ['backup_path' => $backup, 'protected_sha256' => $protected];
        });
    }

    private function protectedDigest(int $personId, int $yearId): string
    {
        $histories = StudentAcademicHistory::where('person_id', $personId)->orderBy('id')->get();
        $years = StudentAcademicHistoryYear::whereIn('student_academic_history_id', $histories->modelKeys())->where('id', '!=', $yearId)
            ->with(['records' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get();

        return $this->digest(['histories' => $histories->toArray(), 'years' => $years->toArray()]);
    }

    private function without(array $value, array $keys): array
    {
        return array_diff_key($value, array_flip($keys));
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
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
