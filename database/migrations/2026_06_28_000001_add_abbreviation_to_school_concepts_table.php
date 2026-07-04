<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_concepts', function (Blueprint $table): void {
            $table->string('abbreviation', 20)->nullable()->after('name');
        });

        DB::table('school_concepts')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($concept): void {
                DB::table('school_concepts')
                    ->where('id', $concept->id)
                    ->update([
                        'abbreviation' => Str::upper(Str::substr((string) $concept->name, 0, 3)),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('school_concepts', function (Blueprint $table): void {
            $table->dropColumn('abbreviation');
        });
    }
};
