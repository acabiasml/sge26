<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->unsignedTinyInteger('dependency_component_limit')
                ->nullable()
                ->after('active');
        });

        Schema::create('school_concepts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('minimum_score', 5, 2)->nullable();
            $table->decimal('maximum_score', 5, 2)->nullable();
            $table->boolean('minimum_inclusive')->default(true);
            $table->boolean('maximum_inclusive')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_concepts');

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn('dependency_component_limit');
        });
    }
};
