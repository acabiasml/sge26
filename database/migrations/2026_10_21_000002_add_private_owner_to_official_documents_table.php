<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->foreignId('private_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('official_documents')
            ->where('visibility', 'private')
            ->update(['private_owner_user_id' => DB::raw('created_by_user_id')]);
    }

    public function down(): void
    {
        Schema::table('official_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('private_owner_user_id');
        });
    }
};
