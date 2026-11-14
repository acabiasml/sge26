<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Support\EnglishComponentUnifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnglishComponentUnifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_unifies_english_preserving_second_semester_grades_attendance_schedule_and_annual_hours(): void
    {
        [$keep, $remove, $loads, $enrollment, $schedule, $assignment, $historyYear] = $this->scenario();
        $service = app(EnglishComponentUnifier::class);
        $grades = DiaryAssessment::with('results')->orderBy('id')->get();
        $authoritativeResultIds = $grades->filter(fn ($a) => $a->curriculum_component_id === $remove->id || $a->academic_period_id === array_key_first($loads))->flatMap->results->pluck('id')->all();
        $attendance = DiaryAttendanceRecord::with('entries')->orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'class_date' => $r->class_date->toDateString(), 'lesson_count' => $r->lesson_count] + ['entries' => $r->entries->toArray()])->all();
        $plan = $service->unify($keep->id, $remove->id, $loads);
        $this->assertSame(1, $plan['pending']);
        $this->assertSame(1, $plan['grades_to_archive']);
        $this->assertSame(2, $plan['grades_to_keep']);
        $this->assertNotNull($remove->fresh());
        $applied = $service->unify($keep->id, $remove->id, $loads, true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        $backup = json_decode(Storage::disk('local')->get($applied['backup_path']), true);
        $this->assertCount(3, $backup['assessments']);
        $this->assertSame(0, $applied['pending']);
        $this->assertNull($remove->fresh());
        $this->assertSame('2 / 2 / 1 / 1', $keep->fresh()->weeklyLessonsLabel());
        $this->assertEquals(480, $keep->course->fresh()->calculatedWorkloadHours());
        $this->assertEquals(46.67, $keep->fresh()->calculatedWorkloadHours());
        $this->assertSame($assignment->id, $schedule->slots()->sole()->school_class_component_id);
        $this->assertDatabaseHas('diary_assessment_results', ['id' => $authoritativeResultIds[1], 'score' => 5]);
        $this->assertSame($authoritativeResultIds, DiaryAssessmentResult::orderBy('id')->pluck('id')->all());
        $this->assertSame($attendance, DiaryAttendanceRecord::with('entries')->orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'class_date' => $r->class_date->toDateString(), 'lesson_count' => $r->lesson_count] + ['entries' => $r->entries->toArray()])->all());
        $this->assertSame(1, $historyYear->records()->count());
        $this->assertEquals(46.67, $historyYear->records()->sole()->workload_hours);
        $this->assertSame('Língua Inglesa', $historyYear->records()->sole()->component->name);
        $this->assertEquals(1.67, $historyYear->records()->sole()->completed_workload_hours);
        $this->assertSame(0, $service->unify($keep->id, $remove->id, $loads)['pending']);
    }

    public function test_integrity_comparison_accepts_a_different_database_order_of_students(): void
    {
        [$keep, $remove, $loads, $enrollment] = $this->scenario();
        $otherPerson = Person::create(['full_name' => 'Outro estudante']);
        $otherEnrollment = $enrollment->replicate();
        $otherEnrollment->person_id = $otherPerson->id;
        $otherEnrollment->save();
        $assessment = DiaryAssessment::where('curriculum_component_id', $remove->id)->firstOrFail();
        $assessment->results()->delete();
        $assessment->results()->create(['student_enrollment_id' => $otherEnrollment->id, 'score' => 8]);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 5]);
        $call = DiaryAttendanceRecord::where('curriculum_component_id', $remove->id)->firstOrFail();
        $call->entries()->delete();
        $call->entries()->create(['student_enrollment_id' => $otherEnrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $service = app(EnglishComponentUnifier::class);
        $plan = $service->unify($keep->id, $remove->id, $loads);
        $result = $service->unify($keep->id, $remove->id, $loads, true, $plan['snapshot_sha256']);
        $this->assertSame(0, $result['pending']);
        $this->assertDatabaseHas('diary_assessment_results', ['diary_assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id, 'score' => 5]);
        $this->assertDatabaseHas('diary_assessment_results', ['diary_assessment_id' => $assessment->id, 'student_enrollment_id' => $otherEnrollment->id, 'score' => 8]);
    }

    public function test_stale_snapshot_prevents_any_grade_or_matrix_changes(): void
    {
        [$keep, $remove, $loads] = $this->scenario();
        $service = app(EnglishComponentUnifier::class);
        $plan = $service->unify($keep->id, $remove->id, $loads);
        $result = DiaryAssessment::where('curriculum_component_id', $remove->id)->first()->results()->first();
        $result->update(['score' => 8]);
        try {
            $service->unify($keep->id, $remove->id, $loads, true, $plan['snapshot_sha256']);
            $this->fail('Uma nota alterada deve invalidar a simulação.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mudaram', $e->getMessage());
        }
        $this->assertNotNull($remove->fresh());
        $this->assertNull($keep->fresh()->period_loads);
        $this->assertSame(3, DiaryAssessment::count());
    }

    public function test_overlapping_attendance_is_rejected_without_losing_any_records(): void
    {
        [$keep, $remove, $loads] = $this->scenario();
        $call = DiaryAttendanceRecord::where('curriculum_component_id', $remove->id)->first()->replicate();
        $call->curriculum_component_id = $keep->id;
        $call->save();
        $this->expectExceptionMessage('conflitantes na mesma data');
        app(EnglishComponentUnifier::class)->unify($keep->id, $remove->id, $loads);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026, 'starts_at' => '2026-02-02', 'ends_at' => '2026-12-22', 'minimum_school_days' => 200]);
        $periods = collect();
        foreach ([['02-02', '04-30'], ['05-01', '07-08'], ['08-03', '09-30'], ['10-01', '12-22']] as $i => [$start,$end]) {
            $periods->push($year->periods()->create(['name' => 'Bimestre '.($i + 1), 'position' => $i + 1, 'starts_at' => '2026-'.$start, 'ends_at' => '2026-'.$end]));
        }
        $course = $year->courses()->create(['name' => '3º Ano', 'stage' => 'medio', 'class_hour_minutes' => 50]);
        $keep = $course->components()->create(['name' => 'Língua Inglesa', 'workload_hours' => 30, 'starts_period_id' => $periods[0]->id, 'ends_period_id' => $periods[1]->id]);
        $remove = $course->components()->create(['name' => 'Língua Inglesa II', 'weekly_lessons' => 1, 'starts_period_id' => $periods[2]->id, 'ends_period_id' => $periods[3]->id]);
        $course->components()->create(['name' => 'Outros', 'workload_hours' => 433.33]);
        $loads = $periods->mapWithKeys(fn ($p, $i) => [$p->id => ['weekly_lessons' => $i < 2 ? 2 : 1, 'workload_hours' => $i < 2 ? 15 : null]])->all();
        $class = $year->classes()->create(['name' => '3º Ano']);
        $class->courses()->attach($course);
        $teacher = Person::create(['full_name' => 'Docente']);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $keep->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        $other = $class->componentAssignments()->create(['curriculum_component_id' => $remove->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        $schedule = $class->schedules()->create(['name' => 'Segundo semestre', 'starts_at' => '2026-08-03']);
        $schedule->slots()->create(['weekday' => 1, 'starts_at' => '14:45:00', 'ends_at' => '15:35:00', 'type' => 'aula', 'school_class_component_id' => $other->id]);
        $person = Person::create(['full_name' => 'Estudante']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
        $enrollment->courses()->attach($course);
        foreach ([[$keep, $periods[0], 6], [$keep, $periods[2], 6.5], [$remove, $periods[2], 5]] as [$component,$period,$score]) {
            $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'title' => 'Avaliação']);
            $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $score]);
        }
        foreach ([[$keep, $periods[0], '2026-02-09'], [$remove, $periods[2], '2026-08-10']] as [$component,$period,$date]) {
            $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => $date, 'lesson_count' => 1]);
            $call->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
            DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => $date, 'content' => 'Conteúdo registrado']);
        }
        $history = StudentAcademicHistory::create(['person_id' => $person->id, 'school_id' => $school->id, 'title' => 'Histórico', 'stage' => 'Ensino Médio']);
        $historyYear = $history->years()->create(['position' => 1, 'source' => 'system', 'student_enrollment_id' => $enrollment->id, 'year' => '2026', 'label' => '3º Ano', 'final_result' => 'Cursando']);
        foreach ([$keep, $remove] as $i => $component) {
            $hc = $history->components()->create(['name' => $component->name, 'position' => $i + 1, 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens']);
            $hc->records()->create(['student_academic_history_year_id' => $historyYear->id, 'curriculum_component_id' => $component->id, 'score_label' => '-', 'result' => 'Cursando', 'workload_hours' => $component->calculatedWorkloadHours()]);
        }

        return [$keep, $remove, $loads, $enrollment, $schedule, $assignment, $historyYear];
    }
}
