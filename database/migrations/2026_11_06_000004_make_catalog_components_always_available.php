<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('history_curriculum_components')->where('active', false)->update(['active' => true]);
    }

    public function down(): void
    {
        // A disponibilidade dos componentes cadastrados não depende mais de inativação.
    }
};
