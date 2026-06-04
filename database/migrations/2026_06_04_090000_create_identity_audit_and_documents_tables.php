<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('full_name');
            $table->string('social_name')->nullable();
            $table->string('cpf', 20)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('institutional_email')->nullable()->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained('people')->nullOnDelete();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('cnpj')->nullable()->unique();
            $table->string('inep')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('number')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('person_school_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->string('role');
            $table->string('position')->nullable();
            $table->boolean('active')->default(true);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'school_id', 'role']);
            $table->index(['school_id', 'role', 'active']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->string('actor_position')->nullable();
            $table->nullableMorphs('auditable');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
        });

        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('verification_code')->unique();
            $table->string('type');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'issued_at']);
            $table->index(['person_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('person_school_roles');
        Schema::dropIfExists('schools');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('people');
    }
};
