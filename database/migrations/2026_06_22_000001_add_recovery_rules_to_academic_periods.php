<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->string('recovery_mode')->default('none')->after('notes');
            $table->unsignedSmallInteger('recovery_weight')->nullable()->after('recovery_mode');
            $table->foreignId('recovery_replaced_rule_id')->nullable()->after('recovery_weight')->constrained('school_assessment_rules')->nullOnDelete();
        });

        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->boolean('is_recovery')->default(false)->after('school_assessment_rule_id');
            $table->string('recovery_mode')->nullable()->after('is_recovery');
            $table->foreignId('recovery_replaced_rule_id')->nullable()->after('recovery_mode')->constrained('school_assessment_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diary_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recovery_replaced_rule_id');
            $table->dropColumn(['is_recovery', 'recovery_mode']);
        });

        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recovery_replaced_rule_id');
            $table->dropColumn(['recovery_mode', 'recovery_weight']);
        });
    }
};
