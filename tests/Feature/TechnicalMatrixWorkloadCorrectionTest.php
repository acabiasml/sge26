<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Support\StudentReportCardBuilder;
use App\Support\TechnicalMatrixWorkloadCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalMatrixWorkloadCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_hours_apply_to_every_enrollment_without_changing_grades_attendance_or_archived_history(): void
    {
        [$request, $course, $cad, $enrollments, $grade, $attendance, $history] = $this->scenario();
        $original = [$grade->fresh()->getAttributes(), $attendance->fresh()->getAttributes(), $history->fresh()->getAttributes()];
        $service = app(TechnicalMatrixWorkloadCorrection::class);
        $plan = $service->correct($request);
        $this->assertSame(1093.34, $plan['from_hours']);
        $this->assertSame(2, $plan['components_to_update']);
        $this->assertSame([400, 400, 400], array_values($plan['module_hours']));
        $this->assertNull($cad[0]->fresh()->workload_hours);
        $applied = $service->correct($request, true, $plan['snapshot_sha256']);
        $this->assertEquals(1200, $course->fresh()->workload_hours);
        foreach ($cad as $component) {
            $this->assertEquals(80, $component->fresh()->workload_hours);
        }
        foreach ($enrollments as $enrollment) {
            $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
            $this->assertSame(1200.0, $report['annualComponents']->sum(fn ($row) => $row['component']->calculatedWorkloadHours()));
        }
        $this->assertSame($original, [$grade->fresh()->getAttributes(), $attendance->fresh()->getAttributes(), $history->fresh()->getAttributes()]);
        $this->assertSame($plan['protected_sha256'], $service->correct($request)['protected_sha256']);
        $again = $service->correct($request);
        $this->assertSame(0, $again['components_to_update']);
        $this->assertFalse($again['course_to_update']);
        Storage::disk('local')->assertExists($applied['backup_path']);
    }

    public function test_changed_grade_invalidates_the_reviewed_snapshot(): void
    {
        [$request, $course, $cad, $enrollments, $grade] = $this->scenario();
        $service = app(TechnicalMatrixWorkloadCorrection::class);
        $plan = $service->correct($request);
        $grade->update(['score' => 8]);
        try {
            $service->correct($request, true, $plan['snapshot_sha256']);
            $this->fail('A conferência desatualizada deveria ser recusada.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('mudaram após', $error->getMessage());
        }
        $this->assertNull($cad[0]->fresh()->workload_hours);
        $this->assertEquals(1093, $course->fresh()->workload_hours);
        $this->assertSame('8.00', $grade->fresh()->score);
    }

    public function test_matching_total_with_the_wrong_distribution_between_modules_is_rejected(): void
    {
        [$request, $course, $cad] = $this->scenario();
        foreach ($request['components'] as &$component) {
            if ($component['id'] === $cad[0]->id) {
                $component['workload_hours'] = 120;
            } elseif ($component['id'] === $cad[1]->id) {
                $component['workload_hours'] = 40;
            }
        }
        unset($component);
        $this->expectExceptionMessage('não fecham os módulos');
        app(TechnicalMatrixWorkloadCorrection::class)->correct($request);
    }

    public function test_cannot_repurpose_the_correction_for_a_regular_matrix(): void
    {
        [$request, $course] = $this->scenario();
        $course->update(['stage' => 'medio']);
        $this->expectExceptionMessage('fora do curso técnico');
        app(TechnicalMatrixWorkloadCorrection::class)->correct($request);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de Teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025-2026', 'reference_year' => 2025,
            'starts_at' => '2025-02-03', 'ends_at' => '2026-12-18', 'minimum_school_days' => 200]);
        $periods = collect();
        foreach ([['2025-02-03', '2025-06-30'], ['2025-08-01', '2025-12-19'], ['2026-02-02', '2026-12-18']] as $i => [$start, $end]) {
            $periods->push($year->periods()->create(['name' => 'Módulo '.($i + 1), 'position' => $i + 1, 'starts_at' => $start, 'ends_at' => $end]));
        }
        $course = $year->courses()->create(['name' => 'Técnico em Móveis', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'workload_hours' => 1093]);
        $class = $year->classes()->create(['name' => '2025-2026']);
        $class->courses()->attach($course);
        $targets = $cad = [];
        foreach ([[120, 120, 60, 100], [80, 80, 60, 120, 60], [80, 60, 200, 60]] as $module => $hours) {
            foreach ($hours as $i => $load) {
                $missing = $module > 0 && $i === 0;
                $component = $course->components()->create(['name' => $missing ? 'Desenho Assistido por Computador '.($module === 1 ? 'I' : 'II') : 'Componente '.($module + 1).'.'.$i,
                    'active' => true, 'starts_period_id' => $periods[$module]->id, 'ends_period_id' => $periods[$module]->id,
                    'workload_hours' => $missing ? null : $load, 'weekly_lessons' => $missing ? 2 : null]);
                $targets[] = ['id' => $component->id, 'workload_hours' => $load];
                if ($missing) {
                    $cad[] = $component;
                }
            }
        }
        $enrollments = collect();
        foreach (['Estudante Um', 'Estudante Dois'] as $name) {
            $person = Person::create(['full_name' => $name]);
            $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-03', 'status' => 'matriculado']);
            $enrollment->courses()->attach($course);
            $enrollments->push($enrollment);
        }
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $cad[0]->id,
            'academic_period_id' => $periods[1]->id, 'title' => 'Avaliação', 'assessment_date' => '2025-09-01']);
        $grade = $assessment->results()->create(['student_enrollment_id' => $enrollments[0]->id, 'score' => 7.5]);
        $record = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $cad[0]->id,
            'academic_period_id' => $periods[1]->id, 'class_date' => '2025-09-01', 'lesson_count' => 2]);
        $attendance = $record->entries()->create(['student_enrollment_id' => $enrollments[0]->id, 'status' => 'ausente', 'attended_lessons' => 0]);
        $history = $enrollments[0]->student->academicHistories()->create(['school_id' => $school->id, 'education_stage' => 'tecnico', 'technical_course_id' => $course->id]);
        $history = $history->years()->create(['student_enrollment_id' => $enrollments[0]->id, 'source' => 'system', 'label' => 'Curso técnico', 'workload_hours' => 1093.34]);
        $request = ['format' => 'documented-technical-matrix-workloads-v1', 'user_confirmed' => true, 'school_id' => $school->id,
            'course_id' => $course->id, 'reference_year' => 2025, 'source_pdf_sha256' => str_repeat('a', 64), 'components' => $targets,
            'expected_module_hours' => $periods->mapWithKeys(fn ($p) => [$p->id => 400])->all(), 'expected_total_hours' => 1200];

        return [$request, $course, $cad, $enrollments, $grade, $attendance, $history];
    }
}
