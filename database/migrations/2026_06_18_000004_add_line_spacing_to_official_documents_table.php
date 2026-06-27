<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->decimal('line_spacing', 3, 2)->default(1.5)->after('orientation');
        });
    }

    public function down(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->dropColumn('line_spacing');
        });
    }
};
