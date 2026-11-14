<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\School;
use App\Support\AcademicStructureValidator;
use App\Support\HighSchoolCohortWorkload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighSchoolCohortWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_follow_entry_year_and_accept_different_annual_distributions(): void
    {
        $service = app(HighSchoolCohortWorkload::class);
        foreach ([2022, 2023, 2024, 2025, 2026] as $entry) {
            $school = School::create(['name' => 'Escola '.$entry]);
            $loads = $entry <= 2024 ? [840, 480, 480] : [1000, 800, 600];
            foreach ($loads as $index => $hours) {
                $course = $this->matrix($school, $entry + $index, $index + 1, $hours);
            }
            // Mesmo ano civil, outros ingressos e outra etapa não entram na soma.
            $this->matrix($school, $entry, 3, 900);
            $result = $service->forCourse($course);
            $this->assertSame($entry, $result['entry_year']);
            $this->assertSame($entry <= 2024 ? 1800 : 2400, $result['target_hours']);
            $this->assertSame('complete', $result['status']);
            $this->assertSame(0.0, $result['remaining_hours']);
        }
        $this->assertNull($service->target(2021));
    }

    public function test_missing_year_is_explicit_and_unused_matrices_and_inactive_components_are_excluded(): void
    {
        $school = School::create(['name' => 'Escola']);
        $first = $this->matrix($school, 2025, 1, 1000);
        $second = $this->matrix($school, 2026, 2, 800);
        $this->matrix($school, 2027, 3, 900, false);
        $second->components()->create(['name' => 'Desativado', 'workload_hours' => 90, 'active' => false]);
        $result = app(HighSchoolCohortWorkload::class)->forCourse($first);
        $this->assertSame('incomplete', $result['status']);
        $this->assertSame([3], $result['missing_grades']);
        $this->assertSame(600.0, $result['remaining_hours']);
        $this->assertStringContainsString('600,00 h', collect(AcademicStructureValidator::forCourse($first))->first()['description']);
    }

    public function test_excess_is_detected_and_parallel_classes_do_not_duplicate_the_hours(): void
    {
        $school = School::create(['name' => 'Escola']);
        $first = $this->matrix($school, 2024, 1, 840);
        $first->classes()->first()->academicYear->classes()->create(['name' => 'Outra turma'])->courses()->attach($first);
        $this->matrix($school, 2025, 2, 560);
        $third = $this->matrix($school, 2026, 3, 480);
        $this->assertSame('exceeded', app(HighSchoolCohortWorkload::class)->forCourse($third)['status']);
        $this->assertSame(1880.0, app(HighSchoolCohortWorkload::class)->forCourse($first)['registered_hours']);
    }

    private function matrix(School $school, int $year, int $grade, int $hours, bool $offered = true): AcademicCourse
    {
        $academicYear = AcademicYear::firstOrCreate(['school_id' => $school->id, 'reference_year' => $year],
            ['name' => (string) $year, 'starts_at' => $year.'-02-01', 'ends_at' => $year.'-12-20']);
        $course = $academicYear->courses()->create(['name' => $grade.'º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $area = KnowledgeArea::firstOrCreate(['name' => 'Matemática', 'formation' => 'Formação Geral Básica']);
        $course->components()->create(['name' => 'Matemática', 'knowledge_area_id' => $area->id, 'active' => true, 'workload_hours' => $hours]);
        $itinerary = KnowledgeArea::firstOrCreate(['name' => 'Itinerário Formativo', 'formation' => 'Itinerário Formativo']);
        $course->components()->create(['name' => 'Projeto de Vida', 'knowledge_area_id' => $itinerary->id, 'active' => true, 'workload_hours' => 400]);
        $class = $academicYear->classes()->create(['name' => $grade.'º Ano']);
        $class->courses()->attach($course);
        if ($offered) {
            $student = Person::create(['full_name' => 'Estudante']);
            $class->enrollments()->create(['person_id' => $student->id]);
        }

        return $course;
    }
}
