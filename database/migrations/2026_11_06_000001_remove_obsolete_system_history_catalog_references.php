<?php

use App\Support\HistoryCatalogIntegrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => app(HistoryCatalogIntegrator::class)->pruneObsoleteSystemReferences());
    }

    public function down(): void
    {
        // Referências obsoletas não são restauradas. Notas e séries não são modificadas.
    }
};
