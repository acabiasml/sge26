<?php

namespace App\Support;

use App\Models\StudentAcademicHistory;
use Illuminate\Validation\ValidationException;

class StudentHistoryEditGuard
{
    public function version(StudentAcademicHistory $history): string
    {
        $history->loadMissing(['years', 'components.records']);
        $years = $history->years->filter(fn ($year) => ! $history->is_unified || $year->source !== 'system')->sortBy('id')->values();
        $ids = $years->pluck('id');
        $components = $history->components->sortBy('id')->map(function ($component) use ($ids) {
            $records = $component->records->whereIn('student_academic_history_year_id', $ids)->sortBy('id')->values();

            return $records->isEmpty() ? null : [
                'component' => $component->getAttributes(),
                'records' => $records->map->getAttributes()->all(),
            ];
        })->filter()->values()->all();

        return hash_hmac('sha256', json_encode([
            'history' => $history->only(['id', 'school_id', 'title', 'stage', 'legal_basis', 'notes', 'issued_place', 'issued_date', 'active', 'is_unified', 'education_stage']),
            'years' => $years->map->getAttributes()->all(),
            'components' => $components,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function assertCurrent(StudentAcademicHistory $history, mixed $version): void
    {
        if (! is_string($version) || ! hash_equals($this->version($history), $version)) {
            throw ValidationException::withMessages([
                'history_version' => __('Este histórico mudou ou a tela está desatualizada. Nenhum dado foi salvo. Reabra o histórico e confira os anos antes de tentar novamente.'),
            ]);
        }
    }

    public function assertYearsRetained(StudentAcademicHistory $history, array $years, array $removed): void
    {
        $current = $history->years->filter(fn ($year) => ! $history->is_unified || $year->source !== 'system')->pluck('id')->map(fn ($id) => (int) $id);
        $submitted = collect($years)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $removed = collect($removed)->map(fn ($id) => (int) $id);

        if ($current->diff($submitted->merge($removed))->isNotEmpty()
            || $submitted->merge($removed)->diff($current)->isNotEmpty()
            || $submitted->intersect($removed)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'years' => __('O envio não contém todos os anos já cadastrados. Nenhum dado foi salvo. Reabra o histórico para preservar os lançamentos existentes.'),
            ]);
        }
    }
}
