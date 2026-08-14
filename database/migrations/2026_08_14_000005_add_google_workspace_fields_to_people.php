<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('google_workspace_id')->nullable()->unique()->after('institutional_email');
            $table->timestamp('google_workspace_provisioned_at')->nullable()->after('google_workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropUnique(['google_workspace_id']);
            $table->dropColumn(['google_workspace_id', 'google_workspace_provisioned_at']);
        });
    }
};
