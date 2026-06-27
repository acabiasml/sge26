<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->unsignedTinyInteger('lesson_count')->default(1)->after('class_date');
        });

        Schema::table('diary_attendance_entries', function (Blueprint $table): void {
            $table->unsignedTinyInteger('attended_lessons')->default(0)->after('status');
            $table->json('lesson_presence')->nullable()->after('attended_lessons');
        });

        Schema::create('diary_attendance_justifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('reason');
            $table->foreignId('granted_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_enrollment_id', 'starts_at', 'ends_at'], 'attendance_justification_period_index');
        });

        DB::table('diary_attendance_entries')
            ->orderBy('id')
            ->each(function (object $entry): void {
                $present = $entry->status === 'presente';

                DB::table('diary_attendance_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'attended_lessons' => $present ? 1 : 0,
                        'lesson_presence' => json_encode([$present]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_attendance_justifications');

        Schema::table('diary_attendance_entries', function (Blueprint $table): void {
            $table->dropColumn(['attended_lessons', 'lesson_presence']);
        });

        Schema::table('diary_attendance_records', function (Blueprint $table): void {
            $table->dropColumn('lesson_count');
        });
    }
};
