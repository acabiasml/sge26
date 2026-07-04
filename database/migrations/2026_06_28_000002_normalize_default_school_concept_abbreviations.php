<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_concepts')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($concept): void {
                $name = Str::lower(Str::ascii((string) $concept->name));
                $abbreviation = match (true) {
                    str_contains($name, 'otimo') || str_contains($name, 'timo') => 'O',
                    $name === 'bom' => 'B',
                    $name === 'suficiente' => 'S',
                    $name === 'insuficiente' => 'I',
                    $name === 'insuficiente grave' => 'IG',
                    default => null,
                };

                if ($abbreviation) {
                    DB::table('school_concepts')
                        ->where('id', $concept->id)
                        ->update(['abbreviation' => $abbreviation]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
