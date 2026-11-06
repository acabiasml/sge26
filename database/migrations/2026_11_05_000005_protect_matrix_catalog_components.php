<?php

use App\Support\MatrixHistoryCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_curriculum_components', fn (Blueprint $table) => $table->boolean('matrix_origin')->default(false));
        Schema::create('matrix_catalog_components', function (Blueprint $table): void {
            $table->foreignId('curriculum_component_id')->constrained(indexName: 'matrix_catalog_source_fk')->cascadeOnDelete();
            $table->foreignId('history_curriculum_component_id')->constrained(indexName: 'matrix_catalog_entry_fk')->restrictOnDelete();
            $table->unique('curriculum_component_id', 'matrix_catalog_source_unique');
        });
        DB::transaction(fn () => app(MatrixHistoryCatalog::class)->importAll());
    }

    public function down(): void
    {
        Schema::dropIfExists('matrix_catalog_components');
        Schema::table('history_curriculum_components', fn (Blueprint $table) => $table->dropColumn('matrix_origin'));
    }
};
