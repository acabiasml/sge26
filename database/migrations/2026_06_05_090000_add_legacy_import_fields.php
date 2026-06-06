<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('legacy_id');
            $table->string('legacy_code')->nullable()->after('legacy_source');
            $table->string('student_inep')->nullable()->after('legacy_code');
            $table->json('legacy_metadata')->nullable()->after('student_inep');
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('legacy_id');
            $table->json('legacy_metadata')->nullable()->after('legacy_source');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn([
                'legacy_source',
                'legacy_code',
                'student_inep',
                'legacy_metadata',
            ]);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn([
                'legacy_source',
                'legacy_metadata',
            ]);
        });
    }
};
