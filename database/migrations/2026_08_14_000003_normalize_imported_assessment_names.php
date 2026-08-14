<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_assessment_rules')) {
            return;
        }

        DB::table('school_assessment_rules')
            ->where('name', 'Média do período')
            ->orderBy('id')
            ->get(['id', 'position'])
            ->each(function (object $rule): void {
                $name = 'Avaliação '.max(1, (int) $rule->position);

                DB::table('school_assessment_rules')
                    ->where('id', $rule->id)
                    ->update([
                        'name' => $name,
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('diary_assessments')) {
                    DB::table('diary_assessments')
                        ->where('school_assessment_rule_id', $rule->id)
                        ->where('title', 'Média do período')
                        ->update([
                            'title' => $name,
                            'updated_at' => now(),
                        ]);
                }
            });

        if (Schema::hasTable('diary_assessments')) {
            DB::table('diary_assessments')
                ->whereNull('school_assessment_rule_id')
                ->where('title', 'Média do período')
                ->update([
                    'title' => 'Avaliação 1',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Os nomes anteriores eram internos da importação e não devem voltar à interface.
    }
};
