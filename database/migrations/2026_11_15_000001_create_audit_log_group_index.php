<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_group_states', function (Blueprint $table): void {
            $table->string('scope_key', 64)->primary();
            $table->unsignedBigInteger('last_log_id')->default(0);
            $table->unsignedBigInteger('source_revision')->default(0);
        });
        Schema::create('audit_log_group_rows', function (Blueprint $table): void {
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('first_id');
            $table->unsignedBigInteger('last_id');
            $table->unsignedBigInteger('group_count');
            $table->primary(['scope_key', 'last_id']);
            $table->index(['scope_key', 'first_id']);
        });
        Schema::create('audit_log_group_revision', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('audit_log_group_revision')->insert(['id' => 1, 'revision' => 0]);
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['school_id', 'id']);
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_group_rows');
        Schema::dropIfExists('audit_log_group_states');
        Schema::dropIfExists('audit_log_group_revision');
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'id']);
            $table->dropIndex(['created_at', 'id']);
        });
    }
};
