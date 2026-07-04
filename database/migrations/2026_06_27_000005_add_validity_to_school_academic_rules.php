<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_concepts', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->after('school_id');
        });

        DB::table('school_concepts')
            ->whereNull('effective_from')
            ->update(['effective_from' => now()->toDateString()]);

        Schema::create('school_academic_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedTinyInteger('dependency_component_limit')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'effective_from']);
            $table->index(['school_id', 'effective_from']);
        });

        DB::table('schools')
            ->whereNotNull('dependency_component_limit')
            ->orderBy('id')
            ->get(['id', 'dependency_component_limit'])
            ->each(function ($school): void {
                DB::table('school_academic_criteria')->insert([
                    'school_id' => $school->id,
                    'effective_from' => now()->toDateString(),
                    'dependency_component_limit' => $school->dependency_component_limit,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_academic_criteria');

        Schema::table('school_concepts', function (Blueprint $table): void {
            $table->dropColumn('effective_from');
        });
    }
};
