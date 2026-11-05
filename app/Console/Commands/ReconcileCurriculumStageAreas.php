<?php

namespace App\Console\Commands;

use App\Support\CurriculumAreaStageReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileCurriculumStageAreas extends Command
{
    protected $signature = 'curriculum:reconcile-stage-areas {--apply : Corrigir somente as associações com equivalência conhecida}';

    protected $description = 'Confere as áreas curriculares por etapa sem expor dados de estudantes; por padrão, não altera registros';

    public function handle(CurriculumAreaStageReconciler $reconciler): int
    {
        $apply = (bool) $this->option('apply');
        $result = $apply ? DB::transaction(function () use ($reconciler): array {
            $before = $reconciler->inspect(true);

            return ['mode' => 'apply', 'before' => $before, 'remaining' => $reconciler->inspect()];
        }) : ['mode' => 'read-only', 'diagnosis' => $reconciler->inspect()];
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $apply && ($result['remaining']['curriculum_components'] + $result['remaining']['history_components']) > 0
            ? self::FAILURE : self::SUCCESS;
    }
}
