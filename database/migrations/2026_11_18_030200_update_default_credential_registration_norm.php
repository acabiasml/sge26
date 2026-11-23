<?php

use App\Models\TechnicalProgram;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Só corrige a referência padrão revogada. Não altera PDFs nem snapshots emitidos.
        TechnicalProgram::where('registry_resolution', 'Resolução nº 249/07 – CEE/MT')->each(function ($program) {
            $program->update(['registry_resolution' => 'Resolução Normativa nº 018/2025/CEE-MT']);
        });
    }

    public function down(): void {}
};
