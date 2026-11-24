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
        Schema::table('schools', function (Blueprint $table) {
            $table->string('registry_medium', 8)->default('digital');
        });
        Schema::table('credential_books', function (Blueprint $table) {
            $table->string('medium', 8)->default('digital');
            $table->unique(['school_id', 'kind', 'medium', 'identifier'], 'credential_book_medium_identity');
        });
        Schema::table('credential_books', function (Blueprint $table) {
            $table->dropUnique('credential_book_identity');
        });

        $schools = DB::table('schools')->get(['id', 'name', 'cnpj'])->filter(fn ($school) =>
            in_array(preg_replace('/\D/', '', $school->cnpj ?? ''), ['00176974000201', '00176974000392'], true)
            || in_array(Str::lower(Str::squish(Str::ascii($school->name))), [
                'escola laura vicuna', 'escola particular laura vicuna', 'laura vicuna',
                'escola lar sao domingos savio', 'lar sao domingos savio',
            ], true)
        );
        DB::table('schools')->whereIn('id', $schools->pluck('id'))->update(['registry_medium' => 'physical']);
    }

    public function down(): void
    {
        Schema::table('credential_books', function (Blueprint $table) {
            $table->unique(['school_id', 'kind', 'identifier'], 'credential_book_identity');
        });
        Schema::table('credential_books', function (Blueprint $table) {
            $table->dropUnique('credential_book_medium_identity');
            $table->dropColumn('medium');
        });
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('registry_medium');
        });
    }
};
