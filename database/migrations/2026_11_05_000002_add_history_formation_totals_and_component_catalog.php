<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_history_years', fn (Blueprint $table) => $table->json('formation_workload_hours')->nullable());
        Schema::create('history_curriculum_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_area_id')->constrained()->restrictOnDelete();
            $table->string('education_stage', 20);
            $table->string('formation', 120);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['education_stage', 'formation', 'knowledge_area_id', 'name'], 'history_curriculum_component_unique');
        });
        // Carga inicial do cadastro; as telas passam a consultar somente o banco.
        foreach (config('curriculum.stages', []) as $stage => $definition) {
            foreach ($definition['formations']['formacao_geral_basica']['areas'] ?? [] as $area) {
                $areaId = DB::table('knowledge_areas')->where('name', $area['name'])->value('id');
                if (! $areaId) {
                    $areaId = DB::table('knowledge_areas')->insertGetId(['name' => $area['name'], 'formation' => 'Formação Geral Básica', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($area['components'] as $name) {
                    DB::table('history_curriculum_components')->insert(['knowledge_area_id' => $areaId, 'education_stage' => $stage, 'formation' => 'Formação Geral Básica', 'name' => $name, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('history_curriculum_components');
        Schema::table('student_academic_history_years', fn (Blueprint $table) => $table->dropColumn('formation_workload_hours'));
    }
};
