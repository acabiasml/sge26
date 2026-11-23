<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->unique()->constrained('person_school_roles')->restrictOnDelete();
            $table->string('position', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'position', 'starts_on', 'ends_on'], 'management_substitution_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_substitutions');
    }
};
