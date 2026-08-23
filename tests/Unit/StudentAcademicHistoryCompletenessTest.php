<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryYear;
use App\Support\StudentAcademicHistoryCompleteness;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class StudentAcademicHistoryCompletenessTest extends TestCase
{
    public function test_ninth_elementary_grade_requires_all_previous_elementary_grades(): void
    {
        $result = $this->service()->evaluate($this->history(
            AcademicCourse::STAGE_ELEMENTARY,
            ['1º Ano', '2º Ano', '3º Ano', '4º Ano', '5º Ano', '6º Ano', '8º Ano', '9º Ano'],
        ));

        $this->assertFalse($result['complete']);
        $this->assertSame([7], $result['missing_grades']);
        $this->assertStringContainsString('7º ano', $result['message']);
    }

    public function test_first_high_school_grade_does_not_require_elementary_history(): void
    {
        $result = $this->service()->evaluate($this->history(
            AcademicCourse::STAGE_HIGH_SCHOOL,
            ['1º Ano'],
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame([], $result['missing_grades']);
    }

    public function test_third_high_school_grade_requires_first_and_second_high_school_grades(): void
    {
        $result = $this->service()->evaluate($this->history(
            AcademicCourse::STAGE_HIGH_SCHOOL,
            ['3ª Série'],
        ));

        $this->assertFalse($result['complete']);
        $this->assertSame([1, 2], $result['missing_grades']);
    }

    /** @param list<string> $grades */
    private function history(string $stage, array $grades): StudentAcademicHistory
    {
        $history = new StudentAcademicHistory(['education_stage' => $stage]);
        $history->setRelation('years', new Collection(array_map(
            fn (string $grade): StudentAcademicHistoryYear => new StudentAcademicHistoryYear(['grade_phase' => $grade]),
            $grades,
        )));

        return $history;
    }

    private function service(): StudentAcademicHistoryCompleteness
    {
        return new StudentAcademicHistoryCompleteness;
    }
}
