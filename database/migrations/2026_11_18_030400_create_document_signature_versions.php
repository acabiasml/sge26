<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signature_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issued_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('checked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('signed_path');
            $table->string('signed_sha256', 64);
            $table->string('report_path');
            $table->string('report_sha256', 64);
            $table->string('original_sha256', 64);
            $table->string('signers', 500);
            $table->boolean('complete');
            $table->timestamps();
            $table->unique(['issued_document_id', 'signed_sha256'], 'signed_document_version_digest');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signature_versions');
    }
};
