<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('nis', 20)->nullable()->after('student_inep');
            $table->boolean('receives_federal_aid')->default(false)->after('nis');
        });

        Schema::table('person_contacts', function (Blueprint $table): void {
            $table->string('nis', 20)->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('person_contacts', function (Blueprint $table): void {
            $table->dropColumn('nis');
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['nis', 'receives_federal_aid']);
        });
    }
};
