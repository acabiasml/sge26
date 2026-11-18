<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_operation_failures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('category', 30);
            $table->string('operation');
            $table->string('route_name')->nullable();
            $table->string('method', 12);
            $table->string('path', 1024);
            $table->string('screen_path', 1024)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('message');
            $table->text('reason')->nullable();
            $table->string('exception_class')->nullable();
            $table->string('source_file', 512)->nullable();
            $table->unsignedInteger('source_line')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['category', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_operation_failures');
    }
};
