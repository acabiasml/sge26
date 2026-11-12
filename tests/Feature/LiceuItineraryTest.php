<?php

namespace Tests\Feature;

use App\Models\{AcademicCourse, AcademicPeriod, AcademicYear, CurriculumComponent, Person, School, SchoolClass, StudentEnrollment};
use App\Support\LegacyLiceuItineraryLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiceuItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_selects_only_liceu_furniture_students_with_legacy_high_school_modules_without_writing(): void
    {
        $school = School::create(['name' => LegacyLiceuItineraryLinks::SCHOOL_NAME, 'active' => true]);
        $student = Person::create(['full_name' => 'Estudante de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025, 'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'Médio', 'active' => true]);
        $high = AcademicCourse::create(['academic_year_id' => $year->id, 'name' => '1º Ano', 'stage' => 'medio']);
        $tech = AcademicCourse::create(['academic_year_id' => $year->id, 'name' => 'Técnico em Móveis', 'stage' => 'tecnico']);
        CurriculumComponent::create(['academic_course_id' => $tech->id, 'name' => 'Desenho', 'workload_hours' => 80]);
        $regular = StudentEnrollment::create(['person_id' => $student->id, 'school_class_id' => $class->id, 'status' => 'matriculado', 'enrolled_at' => '2025-02-01']);
        $regular->courses()->attach($high);
        $technical = StudentEnrollment::create(['person_id' => $student->id, 'school_class_id' => $class->id, 'status' => 'matriculado', 'enrolled_at' => '2025-02-01']);
        $technical->courses()->attach($tech);
        $snapshot = app(LegacyLiceuItineraryLinks::class)->snapshot();
        $this->assertSame(1, $snapshot['count']);
        $this->assertSame($regular->id, $snapshot['links'][0]['enrollment_id']);
        $this->assertSame($tech->id, $snapshot['links'][0]['technical_course_id']);
        $this->assertSame([], $snapshot['ambiguous']);
        $this->assertSame($snapshot, app(LegacyLiceuItineraryLinks::class)->snapshot());
        $this->assertDatabaseCount('student_enrollments', 2);
        $this->assertDatabaseCount('student_academic_histories', 0);
    }
}
