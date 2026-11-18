<?php

use App\Support\TechnicalProgramCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => app(TechnicalProgramCatalog::class)->backfill());
    }

    public function down(): void {}
};
