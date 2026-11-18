<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_programs', function (Blueprint $table): void {
            $table->foreignId('knowledge_area_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('offer_modes')->nullable();
        });
        Schema::table('schools', function (Blueprint $table): void {
            foreach (['maintainer', 'creation_act', 'accreditation_act', 'education_system'] as $field) {
                $table->text($field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technical_programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('knowledge_area_id');
            $table->dropColumn('offer_modes');
        });
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn(['maintainer', 'creation_act', 'accreditation_act', 'education_system']));
    }
};
