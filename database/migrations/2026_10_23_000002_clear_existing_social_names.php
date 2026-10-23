<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('people')
            ->whereNotNull('social_name')
            ->update([
                'social_name' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Os nomes removidos não podem ser reconstruídos com segurança.
    }
};
