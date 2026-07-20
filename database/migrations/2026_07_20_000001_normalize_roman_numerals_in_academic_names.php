<?php

use App\Support\TextNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeNames('academic_periods');
        $this->normalizeNames('curriculum_components');
    }

    public function down(): void
    {
        // Capitalization normalization is intentionally irreversible.
    }

    private function normalizeNames(string $table): void
    {
        DB::table($table)
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($records) use ($table): void {
                foreach ($records as $record) {
                    $normalized = TextNormalizer::titleCasePreservingRomanNumerals($record->name);

                    if ($normalized !== $record->name) {
                        DB::table($table)
                            ->where('id', $record->id)
                            ->update(['name' => $normalized]);
                    }
                }
            });
    }
};
