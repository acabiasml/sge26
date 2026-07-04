<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('people')
            ->select(['id', 'birth_city', 'birth_state', 'nationality', 'legacy_metadata'])
            ->orderBy('id')
            ->chunkById(200, function ($people): void {
                foreach ($people as $person) {
                    $metadata = json_decode((string) $person->legacy_metadata, true);

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $updates = [];

                    if (blank($person->birth_city) && filled($metadata['naturalidade'] ?? null)) {
                        $updates['birth_city'] = $metadata['naturalidade'];
                    }

                    if (blank($person->birth_state) && filled($metadata['naturalidade_uf'] ?? null)) {
                        $updates['birth_state'] = mb_strtoupper((string) $metadata['naturalidade_uf']);
                    }

                    if (blank($person->nationality) && filled($metadata['nacionalidade'] ?? null)) {
                        $updates['nationality'] = $metadata['nacionalidade'];
                    }

                    if ($updates !== []) {
                        DB::table('people')->where('id', $person->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Backfill only; intentionally not destructive.
    }
};
