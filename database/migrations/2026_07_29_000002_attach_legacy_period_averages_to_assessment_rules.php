<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diary_assessments') || ! Schema::hasTable('school_assessment_rules')) {
            return;
        }

        $periods = DB::table('diary_assessments')
            ->join('academic_periods', 'diary_assessments.academic_period_id', '=', 'academic_periods.id')
            ->join('academic_years', 'academic_periods.academic_year_id', '=', 'academic_years.id')
            ->whereNull('diary_assessments.school_assessment_rule_id')
            ->where('diary_assessments.is_recovery', false)
            ->select('diary_assessments.academic_period_id', 'academic_years.school_id')
            ->distinct()
            ->get();

        foreach ($periods as $period) {
            $ruleId = $this->periodAverageRuleId((int) $period->school_id, (int) $period->academic_period_id);
            $hasExistingTargetAssessments = DB::table('diary_assessments')
                ->where('academic_period_id', $period->academic_period_id)
                ->where('school_assessment_rule_id', $ruleId)
                ->exists();

            if (! $hasExistingTargetAssessments) {
                DB::table('diary_assessments')
                    ->where('academic_period_id', $period->academic_period_id)
                    ->whereNull('school_assessment_rule_id')
                    ->where('is_recovery', false)
                    ->update([
                        'school_assessment_rule_id' => $ruleId,
                        'title' => 'Média do período',
                        'notes' => null,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('diary_assessments')
                ->where('academic_period_id', $period->academic_period_id)
                ->whereNull('school_assessment_rule_id')
                ->where('is_recovery', false)
                ->chunkById(100, function ($assessments) use ($ruleId): void {
                    foreach ($assessments as $assessment) {
                        $this->attachAssessmentToRule($assessment, $ruleId);
                    }
                });
        }
    }

    public function down(): void
    {
        DB::table('diary_assessments')
            ->whereIn('school_assessment_rule_id', function ($query): void {
                $query->select('id')
                    ->from('school_assessment_rules')
                    ->where('name', 'Média do período');
            })
            ->update(['school_assessment_rule_id' => null]);

        DB::table('school_assessment_rules')
            ->where('name', 'Média do período')
            ->whereNotIn('id', DB::table('diary_assessments')->whereNotNull('school_assessment_rule_id')->select('school_assessment_rule_id'))
            ->delete();
    }

    private function periodAverageRuleId(int $schoolId, int $periodId): int
    {
        $existingAverageRule = DB::table('school_assessment_rules')
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->where('name', 'Média do período')
            ->first();

        if ($existingAverageRule) {
            return (int) $existingAverageRule->id;
        }

        $reusableRule = DB::table('school_assessment_rules')
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->whereNotIn('id', function ($query): void {
                $query->select('school_assessment_rule_id')
                    ->from('diary_assessments')
                    ->join('diary_assessment_results', 'diary_assessments.id', '=', 'diary_assessment_results.diary_assessment_id')
                    ->whereNotNull('school_assessment_rule_id');
            })
            ->orderBy('position')
            ->first();

        if ($reusableRule) {
            DB::table('school_assessment_rules')
                ->where('id', $reusableRule->id)
                ->update([
                    'name' => 'Média do período',
                    'weight' => 1,
                    'maximum_score' => 10,
                    'updated_at' => now(),
                ]);

            return (int) $reusableRule->id;
        }

        $position = ((int) DB::table('school_assessment_rules')
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->max('position')) + 1;

        return (int) DB::table('school_assessment_rules')->insertGetId([
            'school_id' => $schoolId,
            'academic_period_id' => $periodId,
            'name' => 'Média do período',
            'position' => max(1, $position),
            'weight' => 1,
            'maximum_score' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachAssessmentToRule(object $assessment, int $ruleId): void
    {
        $existingAssessment = DB::table('diary_assessments')
            ->where('school_class_id', $assessment->school_class_id)
            ->where('curriculum_component_id', $assessment->curriculum_component_id)
            ->where('academic_period_id', $assessment->academic_period_id)
            ->where('school_assessment_rule_id', $ruleId)
            ->where('id', '<>', $assessment->id)
            ->first();

        if (! $existingAssessment) {
            DB::table('diary_assessments')
                ->where('id', $assessment->id)
                ->update([
                    'school_assessment_rule_id' => $ruleId,
                    'title' => 'Média do período',
                    'notes' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('diary_assessments')
            ->where('id', $existingAssessment->id)
            ->update([
                'legacy_source' => $existingAssessment->legacy_source ?: $assessment->legacy_source,
                'legacy_id' => $existingAssessment->legacy_id ?: $assessment->legacy_id,
                'legacy_metadata' => $existingAssessment->legacy_metadata ?: $assessment->legacy_metadata,
                'title' => 'Média do período',
                'notes' => null,
                'updated_at' => now(),
            ]);

        $results = DB::table('diary_assessment_results')
            ->where('diary_assessment_id', $assessment->id)
            ->get();

        foreach ($results as $result) {
            $existingResult = DB::table('diary_assessment_results')
                ->where('diary_assessment_id', $existingAssessment->id)
                ->where('student_enrollment_id', $result->student_enrollment_id)
                ->first();

            if (! $existingResult) {
                DB::table('diary_assessment_results')
                    ->where('id', $result->id)
                    ->update([
                        'diary_assessment_id' => $existingAssessment->id,
                        'notes' => null,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if ($existingResult->score === null && $result->score !== null) {
                DB::table('diary_assessment_results')
                    ->where('id', $existingResult->id)
                    ->update([
                        'score' => $result->score,
                        'notes' => null,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('diary_assessment_results')->where('id', $result->id)->delete();
        }

        DB::table('diary_assessments')->where('id', $assessment->id)->delete();
    }
};
