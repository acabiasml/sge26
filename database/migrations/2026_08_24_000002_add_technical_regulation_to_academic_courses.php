<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_courses', function (Blueprint $table): void {
            $table->text('technical_legal_basis')->nullable()->after('itinerary_name');
            $table->text('accreditation_act')->nullable()->after('technical_legal_basis');
            $table->text('authorization_act')->nullable()->after('accreditation_act');
            $table->string('regulatory_process')->nullable()->after('authorization_act');
            $table->string('regulatory_opinion')->nullable()->after('regulatory_process');
            $table->string('technological_axis')->nullable()->after('regulatory_opinion');
            $table->string('offer_forms')->nullable()->after('technological_axis');
            $table->string('official_gazette_reference')->nullable()->after('offer_forms');
            $table->date('authorization_starts_at')->nullable()->after('official_gazette_reference');
            $table->date('authorization_ends_at')->nullable()->after('authorization_starts_at');
            $table->text('module_certifications')->nullable()->after('authorization_ends_at');
        });

        Schema::table('student_academic_history_components', function (Blueprint $table): void {
            $table->string('module_label')->nullable()->after('formation');
            $table->text('regulatory_reference')->nullable()->after('module_label');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_history_components', function (Blueprint $table): void {
            $table->dropColumn(['module_label', 'regulatory_reference']);
        });

        Schema::table('academic_courses', function (Blueprint $table): void {
            $table->dropColumn([
                'technical_legal_basis', 'accreditation_act', 'authorization_act',
                'regulatory_process', 'regulatory_opinion', 'technological_axis',
                'offer_forms', 'official_gazette_reference', 'authorization_starts_at',
                'authorization_ends_at', 'module_certifications',
            ]);
        });
    }
};
