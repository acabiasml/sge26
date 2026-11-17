<?php

use App\Support\TechnicalFurnitureProject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => TechnicalFurnitureProject::registerExisting());
    }

    public function down(): void
    {
        // Preserva o cadastro revisado pela escola e os vínculos de documentos emitidos.
    }
};
