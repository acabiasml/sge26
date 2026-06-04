<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('phone');
            $table->string('number')->nullable()->after('address');
            $table->string('district')->nullable()->after('number');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('address_complement')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn([
                'address',
                'number',
                'district',
                'city',
                'state',
                'postal_code',
                'address_complement',
            ]);
        });
    }
};
