<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiaryGradeStatusTest extends TestCase
{
    public function test_status_distinguishes_missing_incomplete_passing_and_recovery_grades(): void
    {
        $average = ['value' => null, 'regular_value' => null, 'recovery_value' => null, 'recovery_required' => false, 'complete' => false, 'completed_assessments' => 0, 'total_assessments' => 3, 'period_passing_score' => 6];
        $render = fn (array $changes) => view('teacher-diaries.partials.grade-status', ['average' => array_replace($average, $changes)])->render();
        $empty = $render([]);
        $this->assertStringContainsString('Sem notas', $empty);
        $this->assertStringNotContainsString('Média alcançada', $empty);
        $incomplete = $render(['value' => 8, 'completed_assessments' => 1]);
        $this->assertStringContainsString('Notas incompletas', $incomplete);
        $this->assertStringNotContainsString('Média alcançada', $incomplete);
        $this->assertStringContainsString('Média alcançada', $render(['value' => 6, 'complete' => true, 'completed_assessments' => 3]));
        $this->assertStringContainsString('Recuperação disponível', $render(['value' => 5, 'complete' => true, 'completed_assessments' => 3, 'recovery_required' => true]));
        $this->assertStringNotContainsString('Média alcançada', $render(['value' => 8, 'complete' => true, 'completed_assessments' => 3, 'period_passing_score' => null]));
    }
}
