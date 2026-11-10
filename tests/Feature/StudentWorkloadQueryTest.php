<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentWorkloadQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_scopes_student_and_year_without_writing_records(): void
    {
        $student = Person::create(['full_name' => 'Estudante da Consulta']);
        $other = Person::create(['full_name' => 'Outro Estudante']);
        $school = School::create(['name' => 'Escola de Teste']);
        $academicYear = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano',
            'reference_year' => 2025, 'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20', 'minimum_school_days' => 200]);
        $course = $academicYear->courses()->create(['name' => '9º Ano', 'stage' => 'fundamental', 'class_hour_minutes' => 50]);
        $course->components()->create(['name' => 'Matemática', 'weekly_lessons' => 5, 'active' => true]);
        $class = $academicYear->classes()->create(['name' => '9º A']);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id]);
        $enrollment->courses()->attach($course);
        foreach ([$student, $other] as $person) {
            $history = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id, 'title' => 'Histórico']);
            foreach ([2024, 2025] as $referenceYear) {
                $history->years()->create(['year' => $referenceYear, 'label' => '9º Ano', 'source' => 'manual', 'workload_hours' => 800]);
            }
        }
        $before = DB::table('audit_logs')->count();
        $report = $this->runWorkloadQuery('estudante da consulta');
        $this->assertSame(1, $report['matched_people']);
        $this->assertSame($student->id, $report['person']['id']);
        $this->assertCount(1, $report['history_years']);
        $this->assertCount(1, $report['enrollments']);
        $this->assertEquals(166.67, $report['enrollments'][0]['courses'][0]['components'][0]['calculated_hours']);
        $this->assertSame($before, DB::table('audit_logs')->count());
        $this->assertDatabaseCount('issued_documents', 0);
        $this->assertDatabaseCount('student_academic_history_years', 4);
    }

    public function test_ambiguous_or_missing_name_does_not_return_student_details(): void
    {
        Person::create(['full_name' => 'Mesmo Nome']);
        Person::create(['full_name' => 'Mesmo Nome']);
        $this->assertSame(['matched_people' => 2], $this->runWorkloadQuery('mesmo nome'));
        $this->assertSame(['matched_people' => 0], $this->runWorkloadQuery('inexistente'));
    }

    private function runWorkloadQuery(string $name): array
    {
        DB::connection()->getPdo()->sqliteCreateFunction('SHA2', fn ($value, $bits) => hash('sha256', $value), 2);
        $source = file_get_contents(base_path('scripts/student-workload-query.php.stub'));
        $source = substr($source, strpos($source, "Illuminate\\Support\\Facades\\DB::statement"));
        $source = str_replace(['SET TRANSACTION READ ONLY', '__NAME_SHA256__', '__REFERENCE_YEAR__'],
            ['PRAGMA query_only = ON', hash('sha256', $name), '2025'], $source);
        ob_start();
        try {
            eval($source);

            return json_decode(ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
            DB::statement('PRAGMA query_only = OFF');
        }
    }
}
