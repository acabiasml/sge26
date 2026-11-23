<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_books', function (Blueprint $table) {
            $table->string('scope', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('credential_books', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
