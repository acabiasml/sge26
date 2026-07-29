<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->nullifyTextMarkers('person_contacts', 'notes');
        $this->nullifyTextMarkers('academic_courses', 'notes');
        $this->nullifyTextMarkers('academic_periods', 'notes');
        $this->nullifyTextMarkers('curriculum_components', 'notes');
        $this->nullifyTextMarkers('student_enrollments', 'notes');
        $this->nullifyTextMarkers('school_classes', 'notes');
        $this->nullifyTextMarkers('diary_attendance_records', 'notes');
        $this->nullifyTextMarkers('diary_assessments', 'notes');
        $this->nullifyTextMarkers('diary_assessment_results', 'notes');

        if (Schema::hasColumn('academic_years', 'notes')) {
            DB::table('academic_years')
                ->where('notes', 'like', 'Importado da base legada%. Revise datas, aprovação e regras antes de usar oficialmente.')
                ->update(['notes' => 'Revise datas, aprovação e regras antes de usar oficialmente.']);

            $this->nullifyTextMarkers('academic_years', 'notes');
        }

        if (Schema::hasColumn('calendar_days', 'description')) {
            DB::table('calendar_days')
                ->where('description', 'like', 'Gerado a partir do período legado%')
                ->update(['description' => null]);
        }

        if (Schema::hasColumn('announcements', 'title')) {
            DB::table('announcements')
                ->where('title', 'Aviso legado')
                ->update(['title' => 'Aviso']);
        }

        if (Schema::hasColumn('diary_assessments', 'title')) {
            DB::table('diary_assessments')
                ->where('title', 'Média legada')
                ->update(['title' => 'Média do período']);
        }

        $this->replaceAuditText('old_values');
        $this->replaceAuditText('new_values');
    }

    public function down(): void
    {
        //
    }

    private function nullifyTextMarkers(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where(function ($query) use ($column): void {
                $query
                    ->where($column, 'like', 'Importado da base legada%')
                    ->orWhere($column, 'like', 'Importado da base%')
                    ->orWhere($column, 'like', 'Registro legado%')
                    ->orWhere($column, 'like', 'Chamada importada da base legada%')
                    ->orWhere($column, 'like', 'Média importada da base legada%')
                    ->orWhere($column, 'like', 'Turma inicial criada a partir do curso legado%');
            })
            ->update([$column => null]);
    }

    private function replaceAuditText(string $column): void
    {
        if (! Schema::hasColumn('audit_logs', $column)) {
            return;
        }

        DB::table('audit_logs')
            ->where($column, 'like', '%legado%')
            ->orWhere($column, 'like', '%legada%')
            ->orWhere($column, 'like', '%importado%')
            ->orWhere($column, 'like', '%importada%')
            ->update([
                $column => DB::raw(
                    "replace(replace(replace(replace(replace(replace(replace(replace(replace(replace({$column}, ' (legado)', ''), ' (Legado)', ''), 'legado', 'anterior'), 'legada', 'anterior'), 'Legado', 'Anterior'), 'Legada', 'Anterior'), 'importado', 'registrado'), 'importada', 'registrada'), 'Importado', 'Registrado'), 'Importada', 'Registrada')"
                ),
            ]);
    }
};
