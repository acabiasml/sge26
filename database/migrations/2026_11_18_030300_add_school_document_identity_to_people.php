<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->boolean('school_cin_confirmed')->default(false);
            $table->string('school_identity_document', 100)->nullable();
            $table->string('school_gender', 60)->nullable();
            $table->string('school_voter_registration', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->dropColumn(['school_cin_confirmed', 'school_identity_document', 'school_gender', 'school_voter_registration']));
    }
};
