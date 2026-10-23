<?php

use App\Models\AcademicYear;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    DB::transaction(function (): void {
        $candidateIds = AcademicYear::query()
            ->whereNull('closed_at')
            ->whereDoesntHave('days', fn ($query) => $query
                ->where('counts_as_school_day', true)
                ->whereBetween('date', ['2026-01-01', '2026-12-31']))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($candidateIds !== [14]) {
            throw new RuntimeException('O conjunto de anos pendentes mudou desde a auditoria.');
        }

        $year = AcademicYear::query()->lockForUpdate()->findOrFail(14);

        if ((int) $year->school_id !== 1
            || (int) $year->reference_year !== 2019
            || $year->ends_at?->toDateString() !== '2019-12-31'
            || $year->isClosed()
            || $year->days()
                ->where('counts_as_school_day', true)
                ->whereBetween('date', ['2026-01-01', '2026-12-31'])
                ->exists()) {
            throw new RuntimeException('O ano letivo não corresponde ao registro auditado.');
        }

        $year->update([
            'active' => false,
            'closed_at' => now(),
            'closed_by_person_id' => null,
            'closure_notes' => trim(collect([
                $year->closure_notes,
                'Fechamento administrativo retrospectivo em '.now()->format('d/m/Y H:i')
                    .': ano sem dias letivos em 2026. Dados acadêmicos preservados; pendências de consolidação e resultados finais não foram alteradas.',
            ])->filter()->implode("\n\n")),
        ]);

        echo 'CLOSED id=14 school_id=1 year=2019', PHP_EOL;
    });
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fechamento não realizado: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
