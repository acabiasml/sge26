<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateString();

        DB::table('people')->update(['active' => false]);

        DB::table('people')
            ->whereExists(function (Builder $query) use ($today): void {
                $query->selectRaw('1')
                    ->from('person_school_roles')
                    ->whereColumn('person_school_roles.person_id', 'people.id')
                    ->where('person_school_roles.active', true)
                    ->where(function (Builder $roles) use ($today): void {
                        $roles->whereNull('person_school_roles.started_at')
                            ->orWhereDate('person_school_roles.started_at', '<=', $today);
                    })
                    ->where(function (Builder $roles) use ($today): void {
                        $roles->whereNull('person_school_roles.ended_at')
                            ->orWhereDate('person_school_roles.ended_at', '>=', $today);
                    });
            })
            ->update(['active' => true]);
    }

    public function down(): void
    {
        //
    }
};
