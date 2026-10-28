<?php

use App\Models\DiaryAttendanceJustification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_attendance_justifications', function (Blueprint $table): void {
            $table->foreignId('updated_by_person_id')->nullable()->after('granted_by_person_id')->constrained('people')->nullOnDelete();
            $table->unsignedInteger('modification_count')->default(0)->after('updated_by_person_id');
        });

        DB::table('audit_logs')
            ->select('auditable_id')
            ->selectRaw('COUNT(*) AS modification_count, MAX(id) AS last_audit_id')
            ->where('auditable_type', DiaryAttendanceJustification::class)
            ->where('action', 'updated')
            ->groupBy('auditable_id')
            ->orderBy('auditable_id')
            ->chunk(500, function ($groups): void {
                foreach ($groups as $group) {
                    DB::table('diary_attendance_justifications')
                        ->where('id', $group->auditable_id)
                        ->update([
                            'modification_count' => $group->modification_count,
                            'updated_by_person_id' => DB::table('audit_logs')->where('id', $group->last_audit_id)->value('actor_person_id'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('diary_attendance_justifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_person_id');
            $table->dropColumn('modification_count');
        });
    }
};
