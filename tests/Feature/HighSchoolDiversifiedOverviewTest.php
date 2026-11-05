<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\HighSchoolDiversifiedOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HighSchoolDiversifiedOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_identifies_only_diversified_high_school_histories_and_courses_without_writing(): void
    {
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025, 'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31']);
        $area = KnowledgeArea::where('name', 'Parte Diversificada')->firstOrFail();
        foreach (['fundamental', 'medio', 'tecnico'] as $stage) {
            $course = $year->courses()->create(['name' => $stage, 'stage' => $stage]);
            $course->components()->create(['name' => 'Projeto', 'knowledge_area_id' => $area->id]);
            $person = Person::create(['full_name' => 'Estudante '.$stage, 'cpf' => fake()->unique()->numerify('###########')]);
            $history = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id, 'education_stage' => $stage, 'title' => 'Histórico', 'active' => true]);
            $historyYear = $history->years()->create(['label' => '1º Ano', 'year' => '2025', 'source' => 'system', 'school_name' => $school->name]);
            foreach (['Parte Diversificada', 'Linguagens'] as $areaName) {
                $component = $history->components()->create(['name' => 'Projeto', 'knowledge_area' => $areaName]);
                $component->records()->create(['student_academic_history_year_id' => $historyYear->id, 'score_label' => '8,0']);
            }
        }
        $auditCount = DB::table('audit_logs')->count();
        $records = DB::table('student_academic_history_records')->orderBy('id')->get()->toJson();
        $report = app(HighSchoolDiversifiedOverview::class)->build();
        $this->assertSame(1, $report['history_components']);
        $this->assertCount(1, $report['histories']);
        $this->assertSame('Estudante Medio', $report['histories'][0]['student']);
        $this->assertSame('2025', $report['histories'][0]['years'][0]['year']);
        $this->assertCount(1, $report['courses']);
        $this->assertSame('Medio', $report['courses'][0]['course']);
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame($records, DB::table('student_academic_history_records')->orderBy('id')->get()->toJson());
        $this->assertStringNotContainsString('cpf', json_encode($report));
        $this->assertStringNotContainsString('score_label', json_encode($report));
    }
}
