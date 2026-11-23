<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('identifier', 50);
            $table->unsignedSmallInteger('reference_year');
            $table->unsignedInteger('first_number');
            $table->unsignedInteger('next_number');
            $table->unsignedInteger('first_folio');
            $table->date('opened_on');
            $table->text('opening_term');
            $table->string('previous_book')->nullable();
            $table->text('closing_term')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'kind', 'identifier'], 'credential_book_identity');
        });
        Schema::create('credential_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_document_id')->unique()->constrained()->restrictOnDelete();
            $table->string('issuance_key', 64)->nullable()->unique();
            $table->unsignedInteger('number');
            $table->unsignedInteger('folio');
            $table->date('registered_on');
            $table->string('sistec_code', 100)->nullable();
            $table->json('snapshot');
            $table->string('document_sha256', 64);
            $table->foreignId('registered_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['credential_book_id', 'number'], 'credential_registration_number');
            $table->unique(['credential_book_id', 'folio'], 'credential_registration_folio');
        });
        Schema::create('credential_registry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('credential_registration_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('data');
            $table->string('previous_hash', 64);
            $table->string('event_hash', 64);
            $table->timestamp('occurred_at', 6);
        });
        Schema::create('credential_registry_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('credential_registration_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('file_path');
            $table->string('sha256', 64);
            $table->json('metadata');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_registry_files');
        Schema::dropIfExists('credential_registry_events');
        Schema::dropIfExists('credential_registrations');
        Schema::dropIfExists('credential_books');
    }
};
