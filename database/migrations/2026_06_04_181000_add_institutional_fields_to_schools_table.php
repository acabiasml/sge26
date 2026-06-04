<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->date('founded_at')->nullable()->after('inep');
            $table->string('website')->nullable()->after('email');
            $table->text('letterhead_text')->nullable()->after('website');
            $table->string('logo_path')->nullable()->after('letterhead_text');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn([
                'founded_at',
                'website',
                'letterhead_text',
                'logo_path',
            ]);
        });
    }
};
