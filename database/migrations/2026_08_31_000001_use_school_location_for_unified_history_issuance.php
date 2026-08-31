<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_academic_histories as histories')
            ->join('schools as schools', 'schools.id', '=', 'histories.school_id')
            ->where('histories.is_unified', true)
            ->whereNotNull('schools.city')
            ->whereNotNull('schools.state')
            ->select(['histories.id', 'schools.city', 'schools.state'])
            ->orderBy('histories.id')
            ->each(function (object $history): void {
                $city = trim((string) $history->city);
                $state = trim((string) $history->state);

                if ($city === '' || $state === '') {
                    return;
                }

                DB::table('student_academic_histories')
                    ->where('id', $history->id)
                    ->update([
                        'issued_place' => $city.'-'.$state,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // O local anterior era fixo e não pode ser recuperado com segurança.
    }
};
