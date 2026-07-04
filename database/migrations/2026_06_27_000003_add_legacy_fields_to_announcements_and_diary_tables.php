<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('active');
            $table->unsignedBigInteger('legacy_id')->nullable()->after('legacy_source');
            $table->json('legacy_metadata')->nullable()->after('legacy_id');
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::table('diary_contents', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('updated_by_person_id');
            $table->unsignedBigInteger('legacy_id')->nullable()->after('legacy_source');
            $table->json('legacy_metadata')->nullable()->after('legacy_id');
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('updated_by_person_id');
            $table->unsignedBigInteger('legacy_id')->nullable()->after('legacy_source');
            $table->json('legacy_metadata')->nullable()->after('legacy_id');
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->string('legacy_source')->nullable()->after('notes');
            $table->unsignedBigInteger('legacy_id')->nullable()->after('legacy_source');
            $table->json('legacy_metadata')->nullable()->after('legacy_id');
            $table->index(['legacy_source', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->dropIndex(['legacy_source', 'legacy_id']);
            $table->dropColumn(['legacy_source', 'legacy_id', 'legacy_metadata']);
        });

        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->dropIndex(['legacy_source', 'legacy_id']);
            $table->dropColumn(['legacy_source', 'legacy_id', 'legacy_metadata']);
        });

        Schema::table('diary_contents', function (Blueprint $table): void {
            $table->dropIndex(['legacy_source', 'legacy_id']);
            $table->dropColumn(['legacy_source', 'legacy_id', 'legacy_metadata']);
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['legacy_source', 'legacy_id']);
            $table->dropColumn(['legacy_source', 'legacy_id', 'legacy_metadata']);
        });
    }
};
