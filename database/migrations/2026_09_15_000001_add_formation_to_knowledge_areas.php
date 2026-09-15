<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_areas', fn (Blueprint $table) => $table->string('formation')->nullable());
        // Explicit initial associations for the original area catalog, shared across stages.
        DB::table('knowledge_areas')->whereIn('name', [
            'Linguagens', 'Linguagens e suas Tecnologias', 'Matemática', 'Matemática e suas Tecnologias',
            'Ciências da Natureza', 'Ciências da Natureza e suas Tecnologias', 'Ciências Humanas',
            'Ciências Humanas e Sociais Aplicadas', 'Ensino Religioso',
        ])->update(['formation' => 'Formação Geral Básica']);
        DB::table('knowledge_areas')->whereIn('name', ['Parte Diversificada', 'Parte Complementar'])->update(['formation' => 'Parte Complementar']);
        DB::table('knowledge_areas')->whereIn('name', ['Itinerário Formativo', 'Educação Profissional e Tecnológica'])->update(['formation' => 'Itinerário Formativo']);
    }

    public function down(): void
    {
        Schema::table('knowledge_areas', fn (Blueprint $table) => $table->dropColumn('formation'));
    }
};
