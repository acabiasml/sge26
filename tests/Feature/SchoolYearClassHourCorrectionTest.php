<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\SchoolYearClassHourCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SchoolYearClassHourCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_only_selected_school_and_year_and_preserves_documented_matrix_and_diaries(): void
    {
        [$school, $year, $course, $fixed, $record] = $this->scenario();
        $otherYear = $this->year($school, 2026);
        $otherCourse = $otherYear->courses()->create(['name' => '7º Ano', 'class_hour_minutes' => 50, 'workload_hours' => 100]);
        $otherSchool = School::create(['name' => 'Outra escola']);
        $otherSchoolYear = $this->year($otherSchool, 2025);
        $foreignCourse = $otherSchoolYear->courses()->create(['name' => '8º Ano', 'class_hour_minutes' => 50, 'workload_hours' => 200]);
        $grades = DiaryAssessmentResult::get()->toArray();
        $attendance = DiaryAttendanceRecord::with('entries')->get()->toArray();
        $fixedBefore = $fixed->fresh('components')->toArray();
        $service = app(SchoolYearClassHourCorrection::class);
        $plan = $service->correct($school->id, 2025, 60);
        $this->assertSame(1, $plan['courses_to_update']);
        $this->assertSame(2, $plan['components_to_restore']);
        $this->assertEquals(50, $course->fresh()->class_hour_minutes);
        $applied = $service->correct($school->id, 2025, 60, true, $plan['snapshot_sha256']);
        $this->assertEquals(60, $year->fresh()->class_hour_minutes);
        $this->assertEquals(60, $course->fresh()->class_hour_minutes);
        $this->assertEquals(40, $course->components()->where('name', 'Arte')->sole()->workload_hours);
        $this->assertEquals(160, $course->components()->where('name', 'Matemática')->sole()->workload_hours);
        $this->assertEquals(4, $course->components()->where('name', 'Matemática')->sole()->weekly_lessons);
        $this->assertEquals(125, $course->components()->where('name', 'Carga manual')->sole()->workload_hours);
        $this->assertSame($fixedBefore, $fixed->fresh('components')->toArray());
        $this->assertEquals(50, $otherYear->fresh()->class_hour_minutes);
        $this->assertEquals(50, $otherCourse->fresh()->class_hour_minutes);
        $this->assertEquals(50, $otherSchoolYear->fresh()->class_hour_minutes);
        $this->assertEquals(50, $foreignCourse->fresh()->class_hour_minutes);
        $this->assertEquals(40, $record->fresh()->workload_hours);
        $this->assertEquals(1, $record->fresh()->completed_workload_hours);
        $this->assertSame('8,0', $record->fresh()->score_label);
        $this->assertSame($grades, DiaryAssessmentResult::get()->toArray());
        $this->assertSame($attendance, DiaryAttendanceRecord::with('entries')->get()->toArray());
        Storage::disk('local')->assertExists($applied['backup_path']);
        $verified = $service->correct($school->id, 2025, 60);
        $this->assertSame(0, $verified['courses_to_update']);
        $this->assertSame(0, $verified['components_to_restore']);
        $this->assertSame(0, $verified['academic_years_to_update']);
        $this->assertSame($plan['diary_sha256'], $verified['diary_sha256']);
    }

    public function test_rejects_concurrent_grade_change_before_updating_any_matrix(): void
    {
        [$school, $year, $course] = $this->scenario();
        $service = app(SchoolYearClassHourCorrection::class);
        $plan = $service->correct($school->id, 2025, 60);
        DiaryAssessmentResult::sole()->update(['score' => 1]);
        try {
            $service->correct($school->id, 2025, 60, true, $plan['snapshot_sha256']);
            $this->fail('O estado alterado deveria bloquear a aplicação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudou', $error->getMessage());
        }
        $this->assertEquals(50, $course->fresh()->class_hour_minutes);
        $this->assertEquals(50, $year->fresh()->class_hour_minutes);
    }

    public function test_failed_history_refresh_rolls_back_all_duration_and_workload_changes(): void
    {
        [$school, $year, $course, , $record] = $this->scenario();
        $record->update(['curriculum_component_id' => null]);
        $record->component->update(['name' => 'Nome sem correspondência']);
        $service = app(SchoolYearClassHourCorrection::class);
        $plan = $service->correct($school->id, 2025, 60);
        try {
            $service->correct($school->id, 2025, 60, true, $plan['snapshot_sha256']);
            $this->fail('Uma falha no histórico deveria reverter a correção.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('histórico', $error->getMessage());
        }
        $this->assertEquals(50, $year->fresh()->class_hour_minutes);
        $this->assertEquals(50, $course->fresh()->class_hour_minutes);
        $this->assertEquals(33, $course->components()->where('name', 'Arte')->sole()->workload_hours);
    }

    private function year(School $school, int $year): AcademicYear
    {
        return AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano escolar', 'reference_year' => $year,
            'starts_at' => $year.'-01-01', 'ends_at' => $year.'-12-31', 'minimum_school_days' => 200, 'class_hour_minutes' => 50]);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de Teste']);
        $year = $this->year($school, 2025);
        $period = $year->periods()->create(['name' => '1º Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-04-30']);
        $course = $year->courses()->create(['name' => '8º Ano', 'stage' => 'fundamental', 'class_hour_minutes' => 50, 'workload_hours' => 325, 'legacy_source' => 'laura']);
        $component = $course->components()->create(['name' => 'Arte', 'workload_hours' => 33, 'weekly_lessons' => 1, 'active' => true, 'legacy_source' => 'laura', 'legacy_metadata' => ['horas' => '40']]);
        $course->components()->create(['name' => 'Matemática', 'workload_hours' => 167, 'weekly_lessons' => 5, 'active' => true, 'legacy_source' => 'laura', 'legacy_metadata' => ['horas' => '160']]);
        $course->components()->create(['name' => 'Carga manual', 'workload_hours' => 125, 'weekly_lessons' => 2, 'active' => true, 'legacy_source' => 'laura', 'legacy_metadata' => ['horas' => '80']]);
        $fixed = $year->courses()->create(['name' => '9º Ano', 'stage' => 'fundamental', 'class_hour_minutes' => 60, 'workload_hours' => 40, 'legacy_source' => 'laura']);
        $fixed->components()->create(['name' => 'Língua Inglesa', 'workload_hours' => 40, 'weekly_lessons' => 1, 'active' => true, 'legacy_source' => 'laura', 'legacy_metadata' => ['horas' => '200']]);
        $class = $year->classes()->create(['name' => '8º Ano']);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante de Teste']);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'title' => 'Avaliação']);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 0]);
        $attendance = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2025-02-03', 'lesson_count' => 1]);
        $attendance->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $history = StudentAcademicHistory::create(['person_id' => $student->id, 'school_id' => $school->id, 'title' => 'Histórico']);
        $historyYear = $history->years()->create(['student_enrollment_id' => $enrollment->id, 'source' => 'system', 'label' => '8º Ano', 'year' => '2025', 'workload_hours' => 33]);
        $historyComponent = $history->components()->create(['name' => 'Arte', 'position' => 1]);
        $record = $historyComponent->records()->create(['student_academic_history_year_id' => $historyYear->id, 'curriculum_component_id' => $component->id, 'score_label' => '8,0', 'workload_hours' => 33, 'completed_workload_hours' => 0.83]);

        return [$school, $year, $course, $fixed, $record];
    }
}
