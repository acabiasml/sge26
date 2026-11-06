<?php

use App\Support\HistoryCatalogIntegrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_curriculum_components', fn (Blueprint $table) => $table->string('formation', 255)->change());
        Schema::create('history_catalog_usages', function (Blueprint $table): void {
            $table->foreignId('history_curriculum_component_id')->constrained(indexName: 'history_catalog_usage_catalog_fk')->restrictOnDelete();
            $table->foreignId('student_academic_history_component_id')->constrained(indexName: 'history_catalog_usage_history_fk')->cascadeOnDelete();
            $table->unique(['history_curriculum_component_id', 'student_academic_history_component_id'], 'history_catalog_usage_unique');
        });
        DB::transaction(fn () => app(HistoryCatalogIntegrator::class)->importAll());
    }

    public function down(): void
    {
        Schema::dropIfExists('history_catalog_usages');
        // Os itens incorporados ao catálogo são preservados em um rollback.
    }
};
