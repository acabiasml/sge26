<?php

namespace Tests\Unit;

use App\Models\StudentBehaviorGrade;
use Tests\TestCase;

class StudentBehaviorGradeTest extends TestCase
{
    public function test_behavior_preserves_numeric_scores_including_zero_and_missing_values(): void
    {
        foreach ([[8.5, '8,5'], [0, '0,0'], [10, '10,0'], [null, '-']] as [$score, $label]) {
            $this->assertSame($label, (new StudentBehaviorGrade(['score' => $score]))->formattedScore());
        }
    }
}
